<?php
/**
 * DATA TABLE COMPONENT
 * ====================
 * One renderer for every record table in the admin panel. Call it with a
 * config array and it emits the search box, the table, the row actions, the
 * edit modal and the pagination:
 *
 *     data_table([
 *         'id'       => 'users',
 *         'label'    => 'user',
 *         'rows'     => $users_records,
 *         'key'      => 'id',
 *         'update'   => 'actions/update_user.php',
 *         'resource' => 'users',
 *         'search'   => ['name', 'email', 'phone'],
 *         'can_edit' => $isEdit,
 *         'columns'  => [
 *             ['label' => '#',      'field' => 'id'],
 *             ['label' => 'Name',   'field' => 'name',   'edit' => 'name'],
 *             ['label' => 'Status', 'field' => 'status', 'edit' => 'status',
 *              'type'  => 'select', 'options' => ['Active' => 'Active', 'Inactive' => 'Inactive'],
 *              'badge' => ['Active' => 'success', '*' => 'danger']],
 *         ],
 *     ]);
 *
 * WHAT CHANGED, AND WHY
 * ---------------------
 * Editing used to be double-click-a-cell. Three things were wrong with it:
 *
 *   1. It was undiscoverable. The only clue was a line of small grey text
 *      reading "Double Click To Edit Table."
 *   2. `item.editing` was one flag for the whole row, so double-clicking any
 *      single cell turned every cell in that row into an input at once, while
 *      Enter saved only the one field you happened to be in.
 *   3. Most fields were never wired to a save call at all. On the users table,
 *      name / username / email / upline / date all accepted your typing,
 *      closed cleanly on Enter, and discarded the edit. There was no error --
 *      it simply did not save.
 *
 * The modal fixes all three by construction: one obvious Edit button, the
 * whole record on screen at once, and a field is only rendered as an input if
 * the config gives it an `edit` column -- which must be a column the matching
 * actions/update_*.php endpoint accepts. Everything else renders read-only
 * rather than pretending.
 *
 * COLUMN KEYS
 * -----------
 *   label     Column heading.
 *   field     Row property to display.
 *   edit      DB column to send to the update endpoint. Omit for read-only.
 *   value     Row property holding the *raw* value to edit, when `field` holds
 *             a formatted one (e.g. field 'balance' shows "Kes 1,200.00" and
 *             value 'balance_raw' holds 1200.00). Implies a reload on save,
 *             since only the server can re-format the display value.
 *   type      text | number | select | textarea | avatar. Default text.
 *   options   [value => label] for type 'select'.
 *   badge     [value => success|danger|warning|info|muted] pill colouring.
 *             '*' is the fallback.
 *   numeric   true to right-align and use tabular figures.
 *   wide      true to make the modal field span both columns.
 *   hint      Explanatory line under the field in the modal.
 *
 * ACTION KEYS (page-specific row buttons, e.g. Approve / Reject)
 * -------------------------------------------------------------
 *   label     Button text.
 *   style     success | danger | primary.
 *   field     Row property to post as `id`. Defaults to the table key.
 *   post      Extra POST fields, e.g. ['action' => 'Success'].
 *   confirm   Confirmation prompt. '{value}' is replaced with the id posted.
 *   show      false to omit the button entirely (permission gating).
 */

if (!function_exists('data_table')) {

    function data_table(array $config)
    {
        static $assetsEmitted = false;

        $tableId  = $config['id'] ?? 'table';
        $key      = $config['key'] ?? 'id';
        $label    = $config['label'] ?? 'record';
        $columns  = $config['columns'] ?? [];
        $rows     = $config['rows'] ?? [];
        $actions  = $config['actions'] ?? [];
        $update   = $config['update'] ?? null;
        $resource = $config['resource'] ?? null;
        $canEdit  = !empty($config['can_edit']) && $update !== null;
        $canDelete = !empty($config['can_delete']) && $resource !== null;
        $emptyText = $config['empty'] ?? 'Nothing to show yet.';

        // Only actions whose `show` is not explicitly false reach the browser.
        // Gating in PHP rather than with a `hidden` class means an admin
        // without the permission never receives the markup at all.
        $actions = array_values(array_filter($actions, static function ($action) {
            return !array_key_exists('show', $action) || $action['show'];
        }));

        $hasActionColumn = $canEdit || $canDelete || $actions !== [];

        // The browser only needs to know about columns, not about the whole
        // config, so the payload is trimmed to what the component reads.
        $jsConfig = [
            'key'      => $key,
            'label'    => $label,
            'update'   => $update,
            'resource' => $resource,
            'search'   => $config['search'] ?? [],
            'perPage'  => $config['per_page'] ?? 25,
            'columns'  => $columns,
            'canEdit'  => $canEdit,
        ];

        $rowsJson = json_encode(
            array_values($rows),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($rowsJson === false) {
            error_log('[data-table] ' . $tableId . ': ' . json_last_error_msg());
            $rowsJson = '[]';
        }

        $configJson = json_encode($jsConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!$assetsEmitted) {
            $assetsEmitted = true;
            echo '<link rel="stylesheet" href="assets/css/data-table.css">' . "\n";
            data_table_script();
        }
        ?>
<div class="dt-root"
     id="dt-<?= htmlspecialchars($tableId, ENT_QUOTES, 'UTF-8') ?>"
     x-data="dataTable()"
     data-rows='<?= htmlspecialchars($rowsJson, ENT_QUOTES, 'UTF-8') ?>'
     data-config='<?= htmlspecialchars($configJson, ENT_QUOTES, 'UTF-8') ?>'>

    <div class="dt-toolbar">
        <div class="dt-search">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.35-4.35"></path>
            </svg>
            <input type="search"
                   x-model="searchTerm"
                   @input="currentPage = 1"
                   placeholder="Search <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>s..."
                   aria-label="Search <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>s">
        </div>
        <div class="dt-count">
            <span x-text="filteredItems.length"></span>
            <span x-text="filteredItems.length === 1 ? '<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>' : '<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>s'"></span>
            <template x-if="searchTerm">
                <span>matched of <span x-text="items.length"></span></span>
            </template>
        </div>
    </div>

    <template x-if="flash">
        <div class="dt-alert" :class="flashType === 'success' ? 'dt-alert--success' : 'dt-alert--danger'" x-text="flash"></div>
    </template>

    <div class="dt-scroll">
        <table class="dt-table">
            <thead>
                <tr>
                    <?php foreach ($columns as $col): ?>
                        <th class="<?= !empty($col['numeric']) ? 'dt-numeric' : '' ?>"><?= htmlspecialchars($col['label'] ?? '', ENT_QUOTES, 'UTF-8') ?></th>
                    <?php endforeach; ?>
                    <?php if ($hasActionColumn): ?>
                        <th class="dt-right">Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <template x-for="(item, idx) in paginatedItems" :key="item[cfg.key] ?? idx">
                    <tr>
                        <?php foreach ($columns as $col):
                            $field   = $col['field'] ?? '';
                            $type    = $col['type'] ?? 'text';
                            $classes = trim((!empty($col['numeric']) ? 'dt-numeric' : ''));
                            ?>
                            <td class="<?= $classes ?>">
                                <?php if ($type === 'avatar'): ?>
                                    <img class="dt-avatar" src="assets/images/avatar-12.png" alt="">
                                <?php elseif (!empty($col['badge'])): ?>
                                    <span class="dt-badge"
                                          :class="badgeClass(<?= htmlspecialchars(json_encode($col['badge']), ENT_QUOTES, 'UTF-8') ?>, item.<?= $field ?>)"
                                          x-text="item.<?= $field ?>"></span>
                                <?php else: ?>
                                    <span x-text="item.<?= $field ?>"></span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>

                        <?php if ($hasActionColumn): ?>
                            <td class="dt-right">
                                <div class="dt-actions">
                                    <?php foreach ($actions as $i => $action):
                                        $style = $action['style'] ?? 'primary';
                                        ?>
                                        <button type="button"
                                                class="dt-action dt-action--<?= htmlspecialchars($style, ENT_QUOTES, 'UTF-8') ?>"
                                                @click="runAction(cfg.actions[<?= $i ?>], item)">
                                            <?= htmlspecialchars($action['label'] ?? 'Action', ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                    <?php endforeach; ?>

                                    <?php if ($canEdit): ?>
                                        <button type="button" class="dt-action dt-action--primary" @click="openEdit(item)"
                                                :aria-label="'Edit <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> ' + item[cfg.key]">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M6.414 16L16.556 5.858l-1.414-1.414L5 14.586V16h1.414zm.829 2H3v-4.243L14.435 2.322a1 1 0 0 1 1.414 0l2.829 2.829a1 1 0 0 1 0 1.414L7.243 18zM3 20h18v2H3v-2z"></path></svg>
                                            Edit
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($canDelete): ?>
                                        <button type="button" class="dt-action dt-action--danger dt-action--icon" @click="remove(item)"
                                                :aria-label="'Delete <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> ' + item[cfg.key]">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M17 6H22V8H20V21C20 21.5523 19.5523 22 19 22H5C4.44772 22 4 21.5523 4 21V8H2V6H7V3C7 2.44772 7.44772 2 8 2H16C16.5523 2 17 2.44772 17 3V6ZM18 8H6V20H18V8ZM9 11H11V17H9V11ZM13 11H15V17H13V11ZM9 4V6H15V4H9Z"></path></svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                </template>

                <template x-if="filteredItems.length === 0">
                    <tr>
                        <td class="dt-empty" colspan="<?= count($columns) + ($hasActionColumn ? 1 : 0) ?>"
                            x-text="searchTerm ? 'No <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>s match \'' + searchTerm + '\'.' : '<?= htmlspecialchars(addslashes($emptyText), ENT_QUOTES, 'UTF-8') ?>'"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <ul class="dt-pagination" x-show="totalPages > 1" x-cloak>
        <li><button type="button" class="dt-page" @click="goToPage(1)" :disabled="currentPage === 1" aria-label="First page">&laquo;</button></li>
        <li><button type="button" class="dt-page" @click="prevPage()" :disabled="currentPage === 1" aria-label="Previous page">&lsaquo;</button></li>
        <template x-for="page in pageWindow" :key="page">
            <li>
                <button type="button" class="dt-page" :class="page === currentPage ? 'dt-page--active' : ''"
                        x-text="page" @click="goToPage(page)" :aria-current="page === currentPage ? 'page' : false"></button>
            </li>
        </template>
        <li><button type="button" class="dt-page" @click="nextPage()" :disabled="currentPage === totalPages" aria-label="Next page">&rsaquo;</button></li>
        <li><button type="button" class="dt-page" @click="goToPage(totalPages)" :disabled="currentPage === totalPages" aria-label="Last page">&raquo;</button></li>
    </ul>

    <?php if ($canEdit): ?>
        <!-- Edit modal. One instance per table, re-bound to whichever row was clicked. -->
        <div class="dt-modal" x-show="editing" x-cloak @keydown.escape.window="close()" @click.self="close()"
             role="dialog" aria-modal="true" :aria-label="'Edit <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>'">
            <div class="dt-modal__panel" x-ref="panel">
                <div class="dt-modal__head">
                    <div>
                        <div class="dt-modal__title">Edit <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="dt-modal__subtitle" x-text="editing ? '#' + editing[cfg.key] : ''"></div>
                    </div>
                    <button type="button" class="dt-modal__close" @click="close()" aria-label="Close">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z"></path></svg>
                    </button>
                </div>

                <form @submit.prevent="save()">
                    <div class="dt-modal__body">
                        <template x-if="error">
                            <div class="dt-alert dt-alert--danger" x-text="error"></div>
                        </template>

                        <template x-for="col in modalColumns" :key="col.label">
                            <div class="dt-field" :class="[col.wide ? 'dt-field--wide' : '', col.edit ? '' : 'dt-field--readonly']">
                                <label x-text="col.label"></label>

                                <template x-if="col.edit && col.type === 'select'">
                                    <select x-model="draft[col.source]">
                                        <template x-for="opt in col.options" :key="opt.value">
                                            <option :value="opt.value" x-text="opt.label"></option>
                                        </template>
                                    </select>
                                </template>

                                <template x-if="col.edit && col.type === 'textarea'">
                                    <textarea x-model="draft[col.source]"></textarea>
                                </template>

                                <template x-if="col.edit && col.type !== 'select' && col.type !== 'textarea'">
                                    <input :type="col.type === 'number' ? 'number' : 'text'"
                                           :step="col.type === 'number' ? 'any' : false"
                                           x-model="draft[col.source]">
                                </template>

                                <template x-if="!col.edit">
                                    <div class="dt-readonly" x-text="displayValue(col)"></div>
                                </template>

                                <template x-if="col.hint">
                                    <p class="dt-field__hint" x-text="col.hint"></p>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div class="dt-modal__foot">
                        <button type="button" class="dt-btn" @click="close()">Cancel</button>
                        <button type="submit" class="dt-btn dt-btn--primary" :disabled="saving"
                                x-text="saving ? 'Saving...' : 'Save changes'"></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($actions !== []): ?>
        <!--
            Page-specific actions (Approve / Reject) post back to this same page,
            which is where the existing PHP handler already lives. The button
            fills this form in and submits it, so the server side is untouched.
        -->
        <form method="post" x-ref="actionForm" hidden></form>
    <?php endif; ?>
</div>
        <?php
        // The action list is handed to the browser separately from the trimmed
        // config above, because only the click handler needs it.
        if ($actions !== []) {
            echo '<script>document.getElementById("dt-' . htmlspecialchars($tableId, ENT_QUOTES, 'UTF-8') . '").dataset.actions = '
                . json_encode(json_encode($actions, JSON_UNESCAPED_SLASHES)) . ';</script>' . "\n";
        }
    }

    /**
     * The Alpine component. Emitted once per page, as a classic (non-module)
     * script so it runs during parsing -- the theme loads Alpine from a
     * deferred `type="module"` bundle, which executes after the document is
     * parsed, so `window.dataTable` is guaranteed to exist by the time Alpine
     * evaluates any `x-data`.
     */
    function data_table_script()
    {
        ?>
<style>[x-cloak]{display:none!important}</style>
<script>
window.dataTable = function () {
    return {
        cfg: { columns: [], search: [], actions: [], key: 'id', perPage: 25 },
        items: [],
        searchTerm: '',
        currentPage: 1,

        editing: null,   // the live row being edited
        draft: {},       // a copy, so Cancel really cancels
        saving: false,
        error: '',
        flash: '',
        flashType: 'danger',

        init() {
            this.cfg = JSON.parse(this.$el.dataset.config || '{}');
            this.items = JSON.parse(this.$el.dataset.rows || '[]');
            this.cfg.actions = JSON.parse(this.$el.dataset.actions || '[]');

            // Each column carries the row property its editor reads from.
            // When that differs from the displayed property the display is a
            // server-formatted string ("Kes 1,200.00"), and only the server
            // can re-render it -- so saving that column reloads the page.
            this.cfg.columns = (this.cfg.columns || []).map(col => ({
                ...col,
                type: col.type || 'text',
                source: col.value || col.field,
                reload: Boolean(col.value && col.value !== col.field),
                // x-for iterates arrays, not objects, so the [value => label]
                // map from PHP is flattened here rather than in the template.
                options: col.options
                    ? Object.entries(col.options).map(([value, label]) => ({ value, label }))
                    : [],
            }));
        },

        /* -- listing ---------------------------------------------------- */

        get filteredItems() {
            const term = this.searchTerm.trim().toLowerCase();
            if (!term) return this.items;

            const fields = this.cfg.search && this.cfg.search.length
                ? this.cfg.search
                : this.cfg.columns.map(c => c.field);

            return this.items.filter(item =>
                fields.some(f => String(item[f] ?? '').toLowerCase().includes(term))
            );
        },

        get totalPages() {
            return Math.ceil(this.filteredItems.length / this.cfg.perPage) || 1;
        },

        get paginatedItems() {
            // A delete or a filter can strand the viewer past the last page.
            const page = Math.min(this.currentPage, this.totalPages);
            const start = (page - 1) * this.cfg.perPage;
            return this.filteredItems.slice(start, start + this.cfg.perPage);
        },

        get pageWindow() {
            const total = this.totalPages;
            const current = this.currentPage;
            if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1);
            let start = Math.max(1, Math.min(current - 2, total - 4));
            return Array.from({ length: 5 }, (_, i) => start + i);
        },

        goToPage(page) { if (page >= 1 && page <= this.totalPages) this.currentPage = page; },
        nextPage() { this.goToPage(this.currentPage + 1); },
        prevPage() { this.goToPage(this.currentPage - 1); },

        badgeClass(map, value) {
            const tone = map[value] ?? map['*'] ?? 'muted';
            return 'dt-badge--' + tone;
        },

        displayValue(col) {
            if (!this.editing) return '';
            const value = this.editing[col.source];
            return (value === null || value === undefined || value === '') ? '--' : value;
        },

        /* -- editing ---------------------------------------------------- */

        get modalColumns() {
            // The key column is the record's identity, not a field; it is
            // already in the modal's subtitle.
            return this.cfg.columns.filter(c => c.field !== this.cfg.key && c.type !== 'avatar');
        },

        openEdit(item) {
            this.error = '';
            this.editing = item;
            this.draft = {};

            this.modalColumns.forEach(col => {
                this.draft[col.source] = item[col.source] ?? '';
            });

            this.$nextTick(() => {
                const first = this.$refs.panel && this.$refs.panel.querySelector('input, select, textarea');
                if (first) first.focus();
            });
        },

        close() {
            this.editing = null;
            this.draft = {};
            this.error = '';
        },

        async save() {
            if (!this.editing || !this.cfg.update) return;

            const id = this.editing[this.cfg.key];

            const changed = this.modalColumns
                .filter(col => col.edit)
                .filter(col => String(this.draft[col.source] ?? '') !== String(this.editing[col.source] ?? ''))
                .map(col => ({ column: col.edit, source: col.source, value: this.draft[col.source], reload: col.reload }));

            if (!changed.length) { this.close(); return; }

            this.saving = true;
            this.error = '';

            try {
                for (const change of changed) {
                    const res = await fetch(this.cfg.update, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id, field: change.column, value: change.value }),
                    });

                    let data = {};
                    try { data = await res.json(); } catch (e) { /* non-JSON body handled below */ }

                    if (!res.ok || !data.success) {
                        // 403 means the session lapsed or the admin lacks
                        // [edit]; say so instead of a bare status code.
                        if (res.status === 403) {
                            throw new Error('You do not have permission to change ' + change.column + '. Sign in again if your session has expired.');
                        }
                        throw new Error(data.message || ('Could not save ' + change.column + ' (HTTP ' + res.status + ')'));
                    }

                    // Only mirror it locally once the server has confirmed it.
                    this.editing[change.source] = change.value;
                }

                if (changed.some(c => c.reload)) {
                    // A formatted display column changed; let the server
                    // re-render rather than guessing at its formatting here.
                    window.location.reload();
                    return;
                }

                this.close();
                this.notify('Saved.', 'success');
            } catch (err) {
                this.error = err.message;
            } finally {
                this.saving = false;
            }
        },

        /* -- deleting --------------------------------------------------- */

        async remove(item) {
            const id = item[this.cfg.key];

            if (!window.confirm('Delete ' + this.cfg.label + ' #' + id + '? This cannot be undone.')) return;

            try {
                const res = await fetch('actions/delete_record.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, table: this.cfg.resource }),
                });

                let data = {};
                try { data = await res.json(); } catch (e) { /* handled below */ }

                if (!res.ok || !data.success) {
                    throw new Error(data.message || ('Delete failed (HTTP ' + res.status + ')'));
                }

                // The row is dropped from the source array, not just hidden.
                // Hiding it left the count and the page maths counting a
                // record that no longer existed.
                const at = this.items.findIndex(row => row[this.cfg.key] === id);
                if (at > -1) this.items.splice(at, 1);
                if (this.currentPage > this.totalPages) this.currentPage = this.totalPages;

                this.notify('Deleted ' + this.cfg.label + ' #' + id + '.', 'success');
            } catch (err) {
                this.notify(err.message, 'danger');
            }
        },

        /* -- page-specific row actions ---------------------------------- */

        runAction(action, item) {
            if (!action) return;

            const value = item[action.field || this.cfg.key];

            if (action.confirm && !window.confirm(action.confirm.replace('{value}', value))) return;

            const form = this.$refs.actionForm;
            form.innerHTML = '';

            const add = (name, val) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = val;
                form.appendChild(input);
            };

            add('id', value);
            Object.entries(action.post || {}).forEach(([name, val]) => add(name, val));

            // The PHP handlers gate on isset($_POST['submit']), so the field
            // has to be called that -- and a form control named "submit"
            // shadows the form's own submit() method, making form.submit() a
            // TypeError. Going through the prototype sidesteps the shadowing.
            add('submit', '1');

            HTMLFormElement.prototype.submit.call(form);
        },

        notify(message, type) {
            this.flash = message;
            this.flashType = type || 'danger';
            setTimeout(() => { this.flash = ''; }, 4000);
        },
    };
};
</script>
        <?php
    }
}
