<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/** يضمن أن كل مفتاح ترجمة مستخدم في الكود والقوالب موجود بالعربية والإنجليزية، وأن الملفين متطابقان في المفاتيح. */
class TranslationCoverageTest extends TestCase
{
    public function test_every_literal_translation_key_used_exists_in_both_languages(): void
    {
        $missing = [];

        foreach ($this->sourceFiles() as $file) {
            $content = File::get($file);
            preg_match_all("/(?:__|trans|@lang)\(\s*'([a-z_]+(?:\.[A-Za-z0-9_\-]+)+)'\s*[,)]/", $content, $matches);

            foreach (array_unique($matches[1]) as $key) {
                foreach (['ar', 'en'] as $locale) {
                    if (! Lang::hasForLocale($key, $locale)) {
                        $missing[] = "{$locale}: {$key} (".basename($file).')';
                    }
                }
            }
        }

        $this->assertSame([], $missing, "مفاتيح ترجمة ناقصة:\n".implode("\n", array_slice($missing, 0, 40)));
    }

    public function test_arabic_and_english_files_have_identical_keys(): void
    {
        $problems = [];

        foreach (File::files(lang_path('ar')) as $file) {
            $name = $file->getFilename();
            $enPath = lang_path('en/'.$name);
            if (! File::exists($enPath)) {
                $problems[] = "en/{$name} غير موجود";

                continue;
            }

            $ar = array_keys(\Illuminate\Support\Arr::dot(require $file->getPathname()));
            $en = array_keys(\Illuminate\Support\Arr::dot(require $enPath));

            foreach (array_diff($ar, $en) as $key) {
                $problems[] = "{$name}: {$key} ناقص في الإنجليزية";
            }
            foreach (array_diff($en, $ar) as $key) {
                $problems[] = "{$name}: {$key} ناقص في العربية";
            }
        }

        // ملفات إطار Laravel القياسية لا يلزم أن تتطابق تمامًا (مثل validation)
        $problems = array_filter($problems, fn ($p) => ! str_starts_with($p, 'validation.php') && ! str_starts_with($p, 'pagination.php'));

        $this->assertSame([], array_values($problems), implode("\n", array_slice($problems, 0, 40)));
    }

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $files = [];
        foreach ([app_path(), resource_path('views'), base_path('database/seeders')] as $dir) {
            foreach (File::allFiles($dir) as $file) {
                if (str_ends_with($file->getFilename(), '.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
