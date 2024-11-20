<?php

/*
 * This file is part of ACSEO's SyliusTypesense for Sylius.
 * (c) ACSEO <contact@acseo.fr>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ACSEO\SyliusTypesense\Controller;

use ACSEO\SyliusTypesense\Form\Type\ProductSearchType;
use ACSEO\SyliusTypesense\Manager\ProductSearchManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends AbstractController
{
    public function __construct(
        private readonly ProductSearchManager $productSearchManager,
        private readonly int $limit,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $products = [];
        $queryValue = $request->query->get('q', '');
        $page = max(1, (int) $request->query->get('page', 1));

        $form = $this->createForm(ProductSearchType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $queryValue = $data['value'] ?? '*';
        }

        $totalPages = 1;
        $totalResults = 0;

        if ($queryValue) {
            $searchResults = $this->productSearchManager->search($queryValue, $page, $this->limit);
            $products = $searchResults['results'];
            $totalResults = $searchResults['totalResults'];
            $totalPages = (int) ceil($totalResults / $this->limit);
        }

        return $this->render('@SyliusShop/Search/index.html.twig', [
            'form' => $form->createView(),
            'products' => $products,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalResults' => $totalResults,
        ]);
    }
}
