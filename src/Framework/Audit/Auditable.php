<?php

namespace Echo\Framework\Audit;

/**
 * Trait Auditable
 *
 * Marker trait for models that should be audit logged.
 * When a model uses this trait, the AuditListener will automatically
 * log create, update, and delete operations via the event system.
 *
 * Previously this trait overrode CRUD methods directly. Now audit logging
 * is decoupled through model lifecycle events (ModelCreated, ModelUpdated,
 * ModelDeleted) handled by AuditListener.
 */
trait Auditable
{
    // Marker trait — presence is checked by AuditListener
}
