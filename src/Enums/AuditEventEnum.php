<?php

namespace Vcoder7\Ltools\Enums;

/**
 * The kind of event recorded on an audit_logs row.
 *
 * Model lifecycle events are produced automatically by the Auditable trait;
 * the remaining events are produced by explicit Audit::auth()/export()/download()/
 * permission() calls at the relevant boundaries.
 */
enum AuditEventEnum: string
{
    // Model lifecycle (Auditable trait)
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';

    // Authentication
    case Login = 'login';
    case Logout = 'logout';
    case LoginFailed = 'login_failed';
    case OtpRequested = 'otp_requested';

    // Data egress
    case Export = 'export';
    case Download = 'download';

    // Authorization
    case PermissionChanged = 'permission_changed';
}
