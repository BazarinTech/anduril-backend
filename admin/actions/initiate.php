<?php
/**
 * Bootstrap + authorization guard for the admin action endpoints.
 *
 * All nine action files include this on their second line, so the guard here
 * covers every one of them. Before Phase 1 these endpoints had no session
 * check at all: an unauthenticated POST could set any wallet balance, grant
 * itself admin permissions, mark any transaction Success, or delete rows from
 * an arbitrary table.
 *
 * Every action in this directory mutates data, so they all require the same
 * thing: a signed-in admin holding the 'edit' permission. That matches what
 * the panel already does visually -- every edit and delete control is rendered
 * behind `$isEdit` -- it just was not enforced on the server.
 *
 * If a legitimate admin starts getting 403s here, their `admins.permissions`
 * column is missing `[edit]`.
 */

require_once __DIR__ . '/../../bootstrap/admin.php';

require_admin_json($query, $fileGetContent, 'edit');
