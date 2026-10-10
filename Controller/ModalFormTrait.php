<?php

declare(strict_types=1);

namespace KimaiPlugin\KimaiExpensesCommunityBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Helpers for controllers whose create/edit actions open in Kimai's modal
 * ("modal-ajax-form" links).
 */
trait ModalFormTrait
{
    /**
     * Whether the form is being loaded into Kimai's modal rather than as a page.
     *
     * The XHR header is what Kimai's own controllers rely on. Our modal links
     * also carry "?modal=1" (and so do the form actions), so the markup is still
     * right if a Kimai version stops sending that header.
     */
    private function isModalRequest(Request $request): bool
    {
        return $request->isXmlHttpRequest() || $request->query->getBoolean('modal');
    }

    /**
     * The answer to a successful modal save: "201 Created" plus an
     * x-modal-redirect header tells Kimai's modal to close and go to that URL.
     * (A plain redirect would be followed by the browser's fetch and the whole
     * target page would end up inside the modal.)
     */
    private function modalSaved(string $redirectUrl): Response
    {
        return new Response('', Response::HTTP_CREATED, ['x-modal-redirect' => $redirectUrl]);
    }
}
