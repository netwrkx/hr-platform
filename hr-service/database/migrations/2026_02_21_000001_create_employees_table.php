<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Shared fields
            $table->string('name');
            $table->string('last_name');
            $table->decimal('salary', 12, 2);
            $table->string('country'); // USA | Germany

            // USA-specific fields
            $table->string('ssn')->nullable();
            $table->string('address')->nullable();

            // Germany-specific fields
            $table->string('tax_id')->nullable();
            $table->string('goal')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
