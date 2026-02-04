<?php

namespace Echo\Framework\Http\Traits;

use Echo\Framework\Http\HtmxResponse;
use Echo\Framework\Http\Response;

/**
 * HTMX helper methods for controllers
 *
 * Provides common patterns for HTMX responses
 */
trait HtmxHelpers
{
    /**
     * Return HTML that triggers a table refresh
     */
    protected function htmxTableRefresh(string $html, string $tableSelector = '#table-container'): Response
    {
        return HtmxResponse::make()
            ->retarget($tableSelector)
            ->reswap('innerHTML')
            ->trigger('tableRefreshed')
            ->toResponse($html);
    }

    /**
     * Close a modal and optionally trigger events
     */
    protected function htmxModalClose(array $triggers = []): Response
    {
        $response = HtmxResponse::make()
            ->trigger(array_merge(['modalClose'], $triggers));

        return $response->toResponse('');
    }

    /**
     * Return form validation errors as HTMX response
     */
    protected function htmxFormErrors(array $errors, ?string $formSelector = null): Response
    {
        $html = '<div class="alert alert-danger">';
        $html .= '<ul class="mb-0">';
        foreach ($errors as $messages) {
            foreach ((array)$messages as $message) {
                $html .= '<li>' . htmlspecialchars($message) . '</li>';
            }
        }
        $html .= '</ul></div>';

        $response = HtmxResponse::make()->trigger('validationFailed');

        if ($formSelector) {
            $response->retarget($formSelector . ' .form-errors');
        }

        return $response->toResponse($html);
    }

    /**
     * Render a partial template for HTMX response
     */
    protected function htmxPartial(string $template, array $data = []): Response
    {
        $html = twig()->render($template, $data);
        return new Response($html);
    }

    /**
     * Render partial with custom HTMX headers
     */
    protected function htmxPartialWith(string $template, array $data = [], ?callable $configure = null): Response
    {
        $html = twig()->render($template, $data);
        $htmx = HtmxResponse::make();

        if ($configure) {
            $configure($htmx);
        }

        return $htmx->toResponse($html);
    }

    /**
     * Trigger a client-side redirect
     */
    protected function htmxRedirect(string $url): Response
    {
        return HtmxResponse::make()
            ->redirect($url)
            ->toResponse('');
    }

    /**
     * Trigger a page refresh
     */
    protected function htmxRefresh(): Response
    {
        return HtmxResponse::make()
            ->refresh()
            ->toResponse('');
    }

    /**
     * Return a success toast/notification trigger
     */
    protected function htmxSuccess(string $message, string $html = ''): Response
    {
        return HtmxResponse::make()
            ->trigger(['toast' => ['type' => 'success', 'message' => $message]])
            ->toResponse($html);
    }

    /**
     * Return an error toast/notification trigger
     */
    protected function htmxError(string $message, string $html = ''): Response
    {
        return HtmxResponse::make()
            ->trigger(['toast' => ['type' => 'error', 'message' => $message]])
            ->toResponse($html);
    }

    /**
     * Swap content out-of-band (update multiple elements)
     */
    protected function htmxOobSwap(array $swaps): Response
    {
        $html = '';
        foreach ($swaps as $selector => $content) {
            $html .= sprintf(
                '<div id="%s" hx-swap-oob="true">%s</div>',
                ltrim($selector, '#'),
                $content
            );
        }
        return new Response($html);
    }

    /**
     * Check if current request is HTMX
     */
    protected function isHtmxRequest(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }

    /**
     * Return different response based on HTMX vs regular request
     */
    protected function htmxOr(callable $htmxResponse, callable $regularResponse): Response
    {
        if ($this->isHtmxRequest()) {
            return $htmxResponse();
        }
        return $regularResponse();
    }
}
