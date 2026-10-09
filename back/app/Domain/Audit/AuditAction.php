<?php

namespace App\Domain\Audit;

/**
 * Ações auditadas. Módulos: auth, users, people, events, sectors, meetings, classes, participants, teams,
 * companies, challenges, tasks, occurrences.
 *
 * Pendências/ocorrências: comentário gera só interação (histórico), sem log de auditoria.
 */
final class AuditAction
{
    public const LOGIN = 'LOGIN';

    public const LOGIN_FAILED = 'LOGIN_FAILED';

    public const LOGIN_BLOCKED_INACTIVE = 'LOGIN_BLOCKED_INACTIVE';

    public const LOGOUT = 'LOGOUT';

    public const USER_CREATED = 'USER_CREATED';

    public const USER_UPDATED = 'USER_UPDATED';

    public const USER_ROLE_CHANGED = 'USER_ROLE_CHANGED';

    public const USER_ACTIVATED = 'USER_ACTIVATED';

    public const USER_INACTIVATED = 'USER_INACTIVATED';

    public const PERSON_CREATED = 'PERSON_CREATED';

    public const PERSON_UPDATED = 'PERSON_UPDATED';

    public const USER_SECTOR_CHANGED = 'USER_SECTOR_CHANGED';

    public const EVENT_CREATED = 'EVENT_CREATED';

    public const EVENT_UPDATED = 'EVENT_UPDATED';

    public const EVENT_DAY_CREATED = 'EVENT_DAY_CREATED';

    public const EVENT_DAY_UPDATED = 'EVENT_DAY_UPDATED';

    public const SECTOR_CREATED = 'SECTOR_CREATED';

    public const SECTOR_UPDATED = 'SECTOR_UPDATED';

    public const SECTOR_ACTIVATED = 'SECTOR_ACTIVATED';

    public const SECTOR_INACTIVATED = 'SECTOR_INACTIVATED';

    public const MEETING_CREATED = 'MEETING_CREATED';

    public const MEETING_UPDATED = 'MEETING_UPDATED';

    public const CLASS_CREATED = 'CLASS_CREATED';

    public const CLASS_UPDATED = 'CLASS_UPDATED';

    public const CLASS_ACTIVATED = 'CLASS_ACTIVATED';

    public const CLASS_INACTIVATED = 'CLASS_INACTIVATED';

    public const PARTICIPANT_CREATED = 'PARTICIPANT_CREATED';

    public const PARTICIPANT_UPDATED = 'PARTICIPANT_UPDATED';

    public const PARTICIPANT_STATUS_CHANGED = 'PARTICIPANT_STATUS_CHANGED';

    public const TEAM_CREATED = 'TEAM_CREATED';

    public const TEAM_UPDATED = 'TEAM_UPDATED';

    public const PARTICIPANT_ADDED_TO_TEAM = 'PARTICIPANT_ADDED_TO_TEAM';

    public const PARTICIPANT_REMOVED_FROM_TEAM = 'PARTICIPANT_REMOVED_FROM_TEAM';

    /** Movimentação entre equipes: um único log com before/after. */
    public const PARTICIPANT_TEAM_CHANGED = 'PARTICIPANT_TEAM_CHANGED';

    public const COMPANY_CREATED = 'COMPANY_CREATED';

    public const COMPANY_UPDATED = 'COMPANY_UPDATED';

    public const COMPANY_STATUS_CHANGED = 'COMPANY_STATUS_CHANGED';

    public const COMPANY_REPRESENTATIVE_ADDED = 'COMPANY_REPRESENTATIVE_ADDED';

    public const COMPANY_REPRESENTATIVE_UPDATED = 'COMPANY_REPRESENTATIVE_UPDATED';

    public const COMPANY_REPRESENTATIVE_STATUS_CHANGED = 'COMPANY_REPRESENTATIVE_STATUS_CHANGED';

    public const CHALLENGE_CREATED = 'CHALLENGE_CREATED';

    public const CHALLENGE_UPDATED = 'CHALLENGE_UPDATED';

    public const CHALLENGE_STATUS_CHANGED = 'CHALLENGE_STATUS_CHANGED';

    public const CHALLENGE_TEAM_ASSIGNED = 'CHALLENGE_TEAM_ASSIGNED';

    /** Desafio movido de uma equipe para outra: um único log com before/after. */
    public const CHALLENGE_TEAM_CHANGED = 'CHALLENGE_TEAM_CHANGED';

    public const CHALLENGE_TEAM_UNASSIGNED = 'CHALLENGE_TEAM_UNASSIGNED';

    public const TASK_CREATED = 'TASK_CREATED';

    public const TASK_UPDATED = 'TASK_UPDATED';

    public const TASK_STATUS_CHANGED = 'TASK_STATUS_CHANGED';

    public const TASK_FORWARDED = 'TASK_FORWARDED';

    public const TASK_COMPLETED = 'TASK_COMPLETED';

    public const TASK_REOPENED = 'TASK_REOPENED';

    public const OCCURRENCE_CREATED = 'OCCURRENCE_CREATED';

    public const OCCURRENCE_UPDATED = 'OCCURRENCE_UPDATED';

    public const OCCURRENCE_STATUS_CHANGED = 'OCCURRENCE_STATUS_CHANGED';

    public const OCCURRENCE_FORWARDED = 'OCCURRENCE_FORWARDED';

    public const OCCURRENCE_RESOLVED = 'OCCURRENCE_RESOLVED';

    public const OCCURRENCE_REOPENED = 'OCCURRENCE_REOPENED';

    /** Ocorrência gerou pendência: um único log (sem TASK_CREATED separado). */
    public const OCCURRENCE_TASK_GENERATED = 'OCCURRENCE_TASK_GENERATED';
}
