<?php
/**
 * ADMIN CONTEXT BOOTSTRAP
 * =======================
 * For everything under admin/ -- the server-rendered dashboard and its
 * action endpoints.
 *
 * Uses admin/vendor, which is a *different* build of bazarin-php-library than
 * backend/vendor despite both claiming v1.1.0. Do not point this at the
 * backend tree without reading docs/AUTH.md first.
 *
 * Unlike the API context this starts a session, because every admin page and
 * action is expected to run as a logged-in administrator.
 */

$BAZARIN_VENDOR = __DIR__ . '/../admin/vendor/autoload.php';

require_once __DIR__ . '/bootstrap.php';

/**
 * ===========================
 * DATABASE-BACKED SESSION HANDLER
 * ===========================
 * Sessions live in the `sessions` table rather than on disk, so they survive
 * the shared-host session GC and work across processes.
 *
 * The #[\ReturnTypeWillChange] attributes silence the PHP 8.1 deprecation
 * notices that were the single largest contributor to admin/error_log; the
 * attribute is inert on PHP 7.x, so this stays portable.
 */
if (!class_exists('DbSessionHandler')) {
    class DbSessionHandler implements SessionHandlerInterface
    {
        private $pdo;

        public function __construct($pdo)
        {
            $this->pdo = $pdo;
        }

        #[\ReturnTypeWillChange]
        public function open($savePath, $sessionName)
        {
            return true;
        }

        #[\ReturnTypeWillChange]
        public function close()
        {
            return true;
        }

        #[\ReturnTypeWillChange]
        public function read($id)
        {
            $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = :id");
            $stmt->execute([':id' => $id]);

            return $stmt->fetchColumn() ?: '';
        }

        #[\ReturnTypeWillChange]
        public function write($id, $data)
        {
            $stmt = $this->pdo->prepare(
                "REPLACE INTO sessions (id, data, timestamp) VALUES (:id, :data, :ts)"
            );

            return $stmt->execute([':id' => $id, ':data' => $data, ':ts' => time()]);
        }

        #[\ReturnTypeWillChange]
        public function destroy($id)
        {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = :id");

            return $stmt->execute([':id' => $id]);
        }

        #[\ReturnTypeWillChange]
        public function gc($maxlifetime)
        {
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE timestamp < :old");

            return $stmt->execute([':old' => time() - $maxlifetime]);
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    $handler = new DbSessionHandler($db->getConnection());
    session_set_save_handler($handler, true);

    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);

    session_start();
}

/**
 * ===========================
 * ADMIN AUTHORIZATION
 * ===========================
 * One definition of "who is the current admin and what may they do", shared
 * by the rendered pages (admin/includes/main.php) and the JSON action
 * endpoints (admin/actions/initiate.php), so the two cannot drift apart.
 */

/**
 * The signed-in admin, or null.
 *
 * Returns both rows because callers need each: `admins` carries roles and
 * permissions, `users` carries the identity (email, phone).
 */
function admin_identity($query)
{
    static $identity = null;
    static $resolved = false;

    if ($resolved) {
        return $identity;
    }

    $resolved = true;

    if (empty($_SESSION['userID'])) {
        return $identity = null;
    }

    $admins = $query->select('admins', '*', ['userID' => $_SESSION['userID']]);
    $users  = $query->select('users', '*', ['ID' => $_SESSION['userID']]);

    // A session pointing at a deleted user, or at someone whose admin rights
    // were revoked, is not an admin.
    if (empty($admins) || empty($users)) {
        return $identity = null;
    }

    return $identity = ['admin' => $admins[0], 'user' => $users[0]];
}

/**
 * Permissions are stored as a bracketed string, e.g. "[view][edit][add]".
 */
function admin_permissions($query)
{
    $identity = admin_identity($query);

    if ($identity === null) {
        return [];
    }

    $raw = $identity['admin']['permissions'] ?? '';

    return preg_match_all('/\[(.*?)\]/', $raw, $matches) ? $matches[1] : [];
}

function admin_can($query, $permission)
{
    return in_array($permission, admin_permissions($query), true);
}

/**
 * Guard for JSON endpoints. Ends the request on failure.
 *
 * Distinguishes 401 (not signed in) from 403 (signed in, insufficient rights)
 * so the panel can tell "your session expired" from "you may not do that".
 * The body keeps the {success, message} shape the existing admin JavaScript
 * already checks.
 */
function require_admin_json($query, $fileGetContent, $permission = null)
{
    $identity = admin_identity($query);

    if ($identity === null) {
        http_response_code(401);
        $fileGetContent->send_content([
            'success' => false,
            'message' => 'Not signed in. Reload the page and sign in again.',
        ]);
        exit;
    }

    if ($permission !== null && !admin_can($query, $permission)) {
        http_response_code(403);
        $fileGetContent->send_content([
            'success' => false,
            'message' => "Your admin account does not have the '{$permission}' permission.",
        ]);
        exit;
    }

    return $identity;
}

/**
 * Guard for rendered pages. Redirects to the login screen on failure.
 */
function require_admin_page($query)
{
    $identity = admin_identity($query);

    if ($identity === null) {
        if (!empty($_SESSION)) {
            session_destroy();
        }

        header('Location: login');
        exit;
    }

    return $identity;
}
