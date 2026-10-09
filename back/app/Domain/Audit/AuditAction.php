<?php

namespace App\Domain\Audit;

/**
 * Ações auditadas. Módulos: auth, users, people, events, sectors, meetings.
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
}
