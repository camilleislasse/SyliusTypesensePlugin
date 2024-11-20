<?php

/*
 * This file is part of ACSEO's SyliusTypesense for Sylius.
 * (c) ACSEO <contact@acseo.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ACSEO\SyliusTypesense\Resolver;

use ACSEO\SyliusTypesense\Manager\ProductSearchManager;
use ACSEO\TypesenseBundle\Manager\CollectionManager;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Sylius\Bundle\ResourceBundle\Controller\RequestConfiguration;
use Sylius\Bundle\ResourceBundle\Controller\ResourcesResolverInterface;
use Sylius\Bundle\ResourceBundle\Grid\View\ResourceGridView;
use Sylius\Component\Grid\Parameters;
use Sylius\Component\Grid\Provider\GridProviderInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Throwable;

#[AsDecorator('sylius.resource_controller.resources_resolver')]
#[AutoconfigureTag('container.decorator')]
class TypesenseResourcesResolver implements ResourcesResolverInterface
{
    private ResourcesResolverInterface $decoratedResolver;

    private GridProviderInterface $gridProvider;

    private CollectionManager $collectionManager;

    private ProductSearchManager $productSearchManager;

    public function __construct(
        ResourcesResolverInterface $decoratedResolver,
        GridProviderInterface $gridProvider,
        CollectionManager $collectionManager,
        ProductSearchManager $productSearchManager,
        private readonly int $limit,
    ) {
        $this->decoratedResolver = $decoratedResolver;
        $this->gridProvider = $gridProvider;
        $this->collectionManager = $collectionManager;
        $this->productSearchManager = $productSearchManager;
    }

    /** @phpstan-ignore-next-line */
    public function getResources(RequestConfiguration $requestConfiguration, RepositoryInterface $repository)
    {
        try {
            $resources = $this->getDecoratedResources($requestConfiguration, $repository);
            if (!$this->hasTypesenseResources($requestConfiguration)) {
                return $resources;
            }

            $request = $requestConfiguration->getRequest();
            $queryValue = $this->getSearchQueryValue($request);
            $searchResults = $this->productSearchManager->search($queryValue, 1, $this->limit);

            if ($requestConfiguration->hasGrid()) {
                return $this->buildGridView($requestConfiguration, $searchResults['results']);
            }

            return $searchResults['results'];
        } catch (Throwable $e) {
            return $this->getDecoratedResources($requestConfiguration, $repository);
        }
    }

    /** @phpstan-ignore-next-line */
    private function getDecoratedResources(RequestConfiguration $requestConfiguration, RepositoryInterface $repository)
    {
        return $this->decoratedResolver->getResources($requestConfiguration, $repository);
    }

    private function hasTypesenseResources(RequestConfiguration $requestConfiguration): bool
    {
        /** @phpstan-ignore-next-line */
        $currentModel = $requestConfiguration->getMetadata()?->getClass('model');

        return \in_array($currentModel, $this->collectionManager->getManagedClassNames(), true);
    }

    /** @phpstan-ignore-next-line */
    private function getSearchQueryValue($request): string
    {
        return $request->query->all()['criteria']['search']['value'] ?? '*';
    }

    /** @phpstan-ignore-next-line */
    private function buildGridView(RequestConfiguration $requestConfiguration, array $resources)
    {
        $gridDefinition = $this->gridProvider->get($requestConfiguration->getGrid());
        $parameters = new Parameters($requestConfiguration->getRequest()->query->all());

        $adapter = new ArrayAdapter($resources);
        $paginator = new Pagerfanta($adapter);

        $newGridView = new ResourceGridView(
            $paginator,
            $gridDefinition,
            $parameters,
            $requestConfiguration->getMetadata(),
            $requestConfiguration
        );

        return $requestConfiguration->isHtmlRequest() ? $newGridView : $newGridView->getData();
    }
}
