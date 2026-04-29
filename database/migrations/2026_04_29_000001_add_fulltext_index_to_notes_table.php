<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add a FULLTEXT index on (title, content) so the Note::scopeSearch()
     * query can use MATCH...AGAINST instead of slow LIKE '%keyword%' scans.
     *
     * Note: FULLTEXT indexes require MyISAM or InnoDB (MySQL 5.6+).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE notes ADD FULLTEXT INDEX ft_notes_search (title, content)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notes DROP INDEX ft_notes_search');
    }
};
