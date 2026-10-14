<?php

namespace Database\Factories;

use App\Models\CardDataset;
use App\Models\Kategori;
use App\Models\Penyelenggara;
use App\Support\KategoriTemplates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kategori>
 */
class KategoriFactory extends Factory
{
    public function definition(): array
    {
        $template = KategoriTemplates::find('haji');

        return [
            'penyelenggara_id' => Penyelenggara::factory(),
            'name' => $template['name'],
            'slug' => fake()->unique()->slug(2),
            'template' => 'haji',
            'istilah_peserta' => $template['istilah_peserta'],
            'istilah_grup' => $template['istilah_grup'],
            'features' => $template['features'],
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Kategori $kategori): void {
            if ($kategori->card_dataset_id) {
                return;
            }

            $dataset = CardDataset::acrossPenyelenggara()->create([
                'name' => $kategori->name,
                'fields' => KategoriTemplates::withCore(KategoriTemplates::find($kategori->template ?? 'haji')['fields']),
                'penyelenggara_id' => $kategori->penyelenggara_id,
                'kategori_id' => $kategori->id,
            ]);

            $kategori->forceFill(['card_dataset_id' => $dataset->id])->saveQuietly();
        });
    }
}
