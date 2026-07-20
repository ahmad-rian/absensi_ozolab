<?php

use App\Models\CardDataset;
use App\Models\CardForm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Format Data": a reusable set of dynamic fields, decoupled from the layout.
        Schema::create('card_datasets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('created_by')->nullable();
            $table->string('name');
            $table->json('fields');   // [{key,label,type,required,options}]
            $table->timestamps();
        });

        // A layout (CardForm) now references one dataset. `fields` stays on card_forms
        // as a synced mirror so existing readers (generator/public/admin) keep working.
        Schema::table('card_forms', function (Blueprint $table) {
            $table->ulid('card_dataset_id')->nullable()->after('name');
        });

        // Backfill: turn each existing layout's inline fields into a dataset.
        CardForm::query()->get()->each(function (CardForm $form) {
            $dataset = CardDataset::create([
                'created_by' => $form->created_by,
                'name' => $form->name,
                'fields' => $form->fields ?? [],
            ]);
            $form->forceFill(['card_dataset_id' => $dataset->id])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('card_forms', function (Blueprint $table) {
            $table->dropColumn('card_dataset_id');
        });
        Schema::dropIfExists('card_datasets');
    }
};
