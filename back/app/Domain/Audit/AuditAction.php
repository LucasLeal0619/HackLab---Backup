<?php

namespace App\Domain\Audit;

/**
 * Ações auditadas. Módulos: auth, users, people.
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
}
