<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_aggregate_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('subject_type', 80);
            $table->string('subject_key', 160);
            $table->string('label', 200);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'subject_type', 'subject_key'], 'scoring_aggregate_subject_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('scoring_aggregate_subjects'); }
};
