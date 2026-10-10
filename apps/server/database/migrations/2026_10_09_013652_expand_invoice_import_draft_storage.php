<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_imports', function (Blueprint $table): void {
            // Encryption expands JSON; a valid 100-position draft can exceed TEXT.
            $table->longText('draft')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_imports', function (Blueprint $table): void {
            $table->text('draft')->nullable()->change();
        });
    }
};
