<?php

/**
 * @file classes/migration/upgrade/DeleteUserGroupSettings.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class DeleteUserGroupSettings
 *
 * @brief Delete user group plugin settings that are not needed any more,
 * because they have been replaced by machine-readable contributor roles.
 */

namespace APP\plugins\generic\citationStyleLanguage\classes\migration\upgrade;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;

class DeleteUserGroupSettings extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        DB::table('plugin_settings')
            ->where('plugin_name', '=', 'citationstylelanguageplugin')
            ->whereIn('setting_name', ['groupTranslator', 'groupAuthor', 'groupEditor', 'groupChapterAuthor'])
            ->delete();
    }

    /**
     * Rollback the migration.
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
