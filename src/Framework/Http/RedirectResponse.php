<?php

namespace Echo\Framework\Http;

/**
 * A redirect response that handles both standard and HTMX redirects.
 *
 * Usage:
 *   return redirect('/dashboard');
 *   return redirect('/dashboard', 301);
 *   return redirect('/dashboard')->withFlash('success', 'Saved!');
 */
class RedirectResponse extends Response
{
    public function __construct(string $url, int $code = 302)
    {
        parent::__construct('', $code);

        // HTMX requests need HX-Redirect for full page navigation
        if (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true') {
            $this->setHeader('HX-Redirect', $url);
        } else {
            $this->setHeader('Location', $url);
        }
    }

    /**
     * Add a flash message to the redirect.
     */
    public function withFlash(string $type, string $message): static
    {
        \Echo\Framework\Session\Flash::add($type, $message);
        return $this;
    }
}
