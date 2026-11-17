<?php

namespace App\Domain\Saga;

/**
 * Routing keys the OS Service produces (cmd.*) and consumes (evt.*).
 * `cmd.<target>.<verb>` goes to one service; `evt.<producer>.<fact>` is a reply or event.
 */
enum SagaMessage: string
{
    // Commands sent by the orchestrator.
    case ENQUEUE_DIAGNOSIS = 'cmd.execution.enqueue_diagnosis';
    case ENQUEUE_REPAIR = 'cmd.execution.enqueue_repair';
    case CANCEL_TASK = 'cmd.execution.cancel_task';
    case CREATE_BUDGET = 'cmd.billing.create_budget';
    case VOID_BUDGET = 'cmd.billing.void_budget';
    case REQUEST_PAYMENT = 'cmd.billing.request_payment';

    // Replies and events consumed by the orchestrator.
    case DIAGNOSIS_ENQUEUED = 'evt.execution.diagnosis_enqueued';
    case DIAGNOSIS_ENQUEUE_FAILED = 'evt.execution.diagnosis_enqueue_failed';
    case DIAGNOSIS_STARTED = 'evt.execution.diagnosis_started';
    case DIAGNOSIS_COMPLETED = 'evt.execution.diagnosis_completed';
    case REPAIR_ENQUEUED = 'evt.execution.repair_enqueued';
    case REPAIR_ENQUEUE_FAILED = 'evt.execution.repair_enqueue_failed';
    case REPAIR_STARTED = 'evt.execution.repair_started';
    case REPAIR_FINISHED = 'evt.execution.repair_finished';
    case BUDGET_CREATED = 'evt.billing.budget_created';
    case BUDGET_CREATION_FAILED = 'evt.billing.budget_creation_failed';
    case BUDGET_APPROVED = 'evt.billing.budget_approved';
    case BUDGET_REJECTED = 'evt.billing.budget_rejected';
    case PAYMENT_REQUESTED = 'evt.billing.payment_requested';
    case PAYMENT_CONFIRMED = 'evt.billing.payment_confirmed';
}
