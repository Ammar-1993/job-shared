<?php

namespace App\Observers;

use App\Models\Resume;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ResumeObserver
{
    /**
     * Handle the Resume "created" event.
     * Generates a vector embedding for every new resume if not already set.
     */
    public function created(Resume $resume): void
    {
        $this->ensureEmbedding($resume);
    }

    /**
     * Handle the Resume "updated" event.
     * Re-generates the embedding when core content fields change.
     */
    public function updated(Resume $resume): void
    {
        $contentChanged = $resume->wasChanged(['summary', 'skills', 'experience', 'education']);

        if ($contentChanged || empty($resume->vector_embedding)) {
            $this->ensureEmbedding($resume);
        }
    }

    /**
     * Generate and persist a vector embedding for the given resume.
     */
    private function ensureEmbedding(Resume $resume): void
    {
        if (!empty($resume->vector_embedding) && strlen((string) $resume->vector_embedding) > 100) {
            return; // Already has a valid embedding – skip.
        }

        try {
            $text = json_encode([
                'summary'    => $resume->summary,
                'skills'     => $resume->skills,
                'experience' => $resume->experience,
                'education'  => $resume->education,
            ]);

            $response = OpenAI::embeddings()->create([
                'model' => 'text-embedding-3-small',
                'input' => $text,
            ]);

            // Use forceFill + saveQuietly to bypass $fillable guards and
            // avoid triggering another observer loop.
            $resume->forceFill([
                'vector_embedding' => json_encode($response->embeddings[0]->embedding),
            ])->saveQuietly();

            Log::info("ResumeObserver: Generated embedding for Resume ID {$resume->id}");
        } catch (\Exception $e) {
            Log::error("ResumeObserver: Failed to generate embedding for Resume ID {$resume->id} — {$e->getMessage()}");
        }
    }
}
