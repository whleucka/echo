<?php

namespace Echo\Framework\Audit;

use Echo\Framework\Event\EventInterface;
use Echo\Framework\Event\ListenerInterface;
use Echo\Framework\Event\Model\ModelCreated;
use Echo\Framework\Event\Model\ModelUpdated;
use Echo\Framework\Event\Model\ModelDeleted;

/**
 * Audit Listener
 *
 * Listens for model lifecycle events and logs audit entries
 * for models that use the Auditable trait.
 */
class AuditListener implements ListenerInterface
{
    public function handle(EventInterface $event): void
    {
        match (true) {
            $event instanceof ModelCreated => $this->onCreated($event),
            $event instanceof ModelUpdated => $this->onUpdated($event),
            $event instanceof ModelDeleted => $this->onDeleted($event),
            default => null,
        };
    }

    private function onCreated(ModelCreated $event): void
    {
        if (!$this->isAuditable($event->model)) {
            return;
        }

        AuditLogger::logCreated(
            $event->model->getTableName(),
            $event->model->getId(),
            $event->attributes
        );
    }

    private function onUpdated(ModelUpdated $event): void
    {
        if (!$this->isAuditable($event->model)) {
            return;
        }

        AuditLogger::logUpdated(
            $event->model->getTableName(),
            $event->model->getId(),
            $event->oldAttributes,
            $event->newAttributes
        );
    }

    private function onDeleted(ModelDeleted $event): void
    {
        if (!$this->usesAuditableTrait($event->modelClass)) {
            return;
        }

        AuditLogger::logDeleted(
            (new $event->modelClass())->getTableName(),
            $event->modelId,
            $event->attributes
        );
    }

    /**
     * Check if a model instance uses the Auditable trait
     */
    private function isAuditable(object $model): bool
    {
        return $this->usesAuditableTrait(get_class($model));
    }

    /**
     * Check if a class uses the Auditable trait (including parent classes)
     */
    private function usesAuditableTrait(string $class): bool
    {
        $traits = [];
        do {
            $traits = array_merge($traits, class_uses($class, true) ?: []);
        } while ($class = get_parent_class($class));

        return in_array(Auditable::class, $traits);
    }
}
