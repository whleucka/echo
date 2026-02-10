<?php

namespace Echo\Framework\Http;

interface RestfulInterface
{
    public function index(): string;
    public function create(): string;
    public function store(): mixed;
    public function show(int $id): string;
    public function edit(int $id): string;
    public function update(int $id): mixed;
    public function destroy(int $id): mixed;
}
