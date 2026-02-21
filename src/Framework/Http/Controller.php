<?php

namespace Echo\Framework\Http;

use App\Models\User;
use Echo\Framework\Http\Exception\HttpForbiddenException;
use Echo\Framework\Http\Exception\HttpNotFoundException;
use Echo\Framework\Session\Flash;

class Controller implements ControllerInterface
{
    protected ?User $user = null;
    protected ?RequestInterface $request = null;
    private array $headers = [];
    private ?array $jsonBody = null;
    private ?Validator $validator = null;

    private function getJsonBody(): array
    {
        if ($this->jsonBody === null) {
            $this->jsonBody = (array) json_decode(
                file_get_contents('php://input') ?: '{}',
                true
            );
        }
        return $this->jsonBody;
    }

    public function setHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setRequest(RequestInterface $request): void
    {
        $this->request = $request;
    }

    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }

    private function validator(): Validator
    {
        if ($this->validator === null) {
            $this->validator = new Validator();
        }
        return $this->validator;
    }

    public function getValidationErrors(): array
    {
        return $this->validator()->getErrors();
    }

    /**
     * HTMX Trigger
     */
    public function hxTrigger(array|string $opts): void
    {
        if (is_array($opts)) {
            $opts = json_encode($opts);
        }
        $this->setHeader("HX-Trigger", $opts);
    }

    public function validate(array $ruleset = [], mixed $id = null): mixed
    {
        $req     = (array) ($this->request->request->data() ?? []);
        $request = [...$req, ...$this->getJsonBody()];
        return $this->validator()->run($ruleset, $id, $request);
    }

    protected function setValidationMessage(string $rule, string $message): void
    {
        $this->validator()->setMessage($rule, $message);
    }

    protected function addValidationError(string $field, string $message): void
    {
        $this->validator()->addError($field, $message);
    }

    protected function getDefaultTemplateData(): array
    {
        return [
            "app" => config("app"),
            "framework" => config("framework"),
            "flash" => Flash::get(),
            "validationErrors" => $this->getValidationErrors(),
        ];
    }

    public function pageNotFound(): never
    {
        throw new HttpNotFoundException();
    }

    public function permissionDenied(): never
    {
        throw new HttpForbiddenException();
    }

    protected function render(string $template, array $data = []): string
    {
        $twig = twig();
        $data = array_merge($this->getDefaultTemplateData(), $data);
        return $twig->render($template, $data);
    }
}
