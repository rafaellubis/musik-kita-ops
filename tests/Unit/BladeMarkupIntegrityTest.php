<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Penjaga integritas markup Blade.
 *
 * Tiga kelas bug di jahitan Blade → HTML → CSS → JS pernah lolos tanpa satu pun
 * error di sisi PHP: template ter-compile "sukses", markup tetap "valid", tapi
 * perilakunya hilang. Semuanya hanya ketahuan saat halaman dibuka di browser.
 * Lihat docs/superpowers/specs/2026-10-02-mini-concert-event-redesign-design.md § 24.
 *
 * Karena PHP tidak bisa memvalidasi semantik lintas bahasa ini, penjaganya adalah
 * pemeriksaan struktur pada sumber template — dan setiap detektor diuji sendiri oleh
 * test_detectors_are_not_blind() supaya tidak bisa diam-diam buta.
 */
class BladeMarkupIntegrityTest extends TestCase
{
    /** @return array<string, string> path relatif => isi file */
    private function bladeSources(): array
    {
        $sources = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $sources[str_replace('\\', '/', $file->getRelativePathname())] = $file->getContents();
            }
        }

        return $sources;
    }

    public function test_detectors_are_not_blind(): void
    {
        // Bug #2: kutip ganda di dalam nilai atribut Alpine memotong atributnya.
        $terpotong = "<div x-data=\"{ a: 1, // kata \"ini\" di komentar JS\n b: 2 }\">";
        $sehat = "<div x-data=\"{ a: 1, b: 'x' }\">";
        $this->assertNotEmpty($this->truncatedAlpineAttributes($terpotong), 'detektor kutip nyasar buta.');
        $this->assertSame([], $this->truncatedAlpineAttributes($sehat));

        // '<' dan '>' di dalam nilai atribut tidak boleh memutus batas tag.
        $bersudut = "<div x-data=\"{ min: 3 }\" x-show=\"total > 1\" x-init=\"if (a < 5) b = 1\">x</div>";
        $this->assertSame([], $this->truncatedAlpineAttributes($bersudut), 'detektor salah memutus tag.');

        // Bug #1: nama directive di dalam komentar Blade dieksekusi preprocessor.
        $this->assertNotEmpty(
            $this->directivesInsideComments("{{-- blok @php contoh --}}\n@php \$a = 1; @endphp"),
            'detektor directive di komentar buta.',
        );
        $this->assertSame([], $this->directivesInsideComments('{{-- catatan biasa --}}'));

        // Bug #3: opacity utility mati di elemen yang sama dengan animasi fade-in-up.
        $this->assertNotEmpty($this->opacityUtilitiesKilledByAnimation('<div class="opacity-70 fade-in-up">x</div>'));
        $this->assertSame([], $this->opacityUtilitiesKilledByAnimation('<div class="opacity-70">x</div>'));
        $this->assertSame([], $this->opacityUtilitiesKilledByAnimation('<div class="fade-in-up">x</div>'));
    }

    public function test_blade_comments_do_not_name_directives(): void
    {
        foreach ($this->bladeSources() as $path => $source) {
            $this->assertSame(
                [],
                $this->directivesInsideComments($source),
                "Komentar Blade di {$path} menyebut nama directive. Blade mengekstrak blok "
                .'@php…@endphp sebelum komentar dibuang, sehingga span-nya dimulai dari directive '
                .'di dalam komentar dan menelan isi template.',
            );
        }
    }

    public function test_alpine_attributes_are_not_truncated_by_stray_quotes(): void
    {
        foreach ($this->bladeSources() as $path => $source) {
            $this->assertSame(
                [],
                $this->truncatedAlpineAttributes($source),
                "Atribut Alpine di {$path} terpotong tanda kutip nyasar. Sisa ekspresinya jadi "
                .'atribut sampah, komponen Alpine tidak pernah terbentuk, dan tombolnya mati '
                .'tanpa error di sisi server.',
            );
        }
    }

    public function test_opacity_utilities_are_not_killed_by_the_fade_in_animation(): void
    {
        foreach ($this->bladeSources() as $path => $source) {
            $this->assertSame(
                [],
                $this->opacityUtilitiesKilledByAnimation($source),
                "Elemen di {$path} memakai utility opacity bersama animasi fade-in-up. "
                .'Keyframe-nya berakhir di opacity 1 dengan fill-mode both, jadi utility opacity '
                .'di elemen yang sama tidak akan pernah berlaku. Pindahkan ke elemen pembungkus '
                .'yang tidak dianimasikan, atau hapus utility-nya.',
            );
        }
    }

    /**
     * Pemisah tag yang meniru parser HTML: tanda '>' di dalam kutip tidak menutup tag,
     * dan '<' di dalam nilai atribut (mis. di dalam string JS) bukan awal tag baru.
     *
     * @return list<string>
     */
    private function tags(string $source): array
    {
        $tags = [];
        $length = strlen($source);
        $i = 0;

        while ($i < $length) {
            $mulaiTag = $source[$i] === '<'
                && ($i + 1) < $length
                && (ctype_alpha($source[$i + 1]) || $source[$i + 1] === '!' || $source[$i + 1] === '/');

            if (! $mulaiTag) {
                $i++;
                continue;
            }

            $start = $i;
            $i++;
            $quote = null;

            while ($i < $length) {
                $char = $source[$i];

                if ($quote !== null) {
                    if ($char === $quote) {
                        $quote = null;
                    }
                } elseif ($char === '"' || $char === "'") {
                    $quote = $char;
                } elseif ($char === '>') {
                    break;
                }

                $i++;
            }

            if ($i >= $length) {
                break;
            }

            $tags[] = substr($source, $start, $i - $start + 1);
            $i++;
        }

        return $tags;
    }

    /**
     * Buang ekspresi Blade supaya karakter '<', '>' dan '->' di dalamnya tidak
     * mengacaukan pembacaan batas tag.
     */
    private function withoutBladeExpressions(string $source): string
    {
        return (string) preg_replace([
            '/\{\{--.*?--\}\}/s',
            '/@php.*?@endphp/s',
            '/\{!!.*?!!\}/s',
            '/\{\{.*?\}\}/s',
            '/@[a-zA-Z]+\([^()]*\)/',
        ], 'X', $source);
    }

    /** @return list<string> */
    private function directivesInsideComments(string $source): array
    {
        $hits = [];

        foreach ($this->bladeComments($source) as $comment) {
            if (preg_match('/@[a-zA-Z][a-zA-Z0-9_]*(?=\s|\(|\$|$)/m', $comment, $directive)) {
                $hits[] = $directive[0];
            }
        }

        return $hits;
    }

    /** @return list<string> */
    private function bladeComments(string $source): array
    {
        return preg_match_all('/\{\{--(.*?)--\}\}/s', $source, $matches) ? $matches[1] : [];
    }

    /**
     * Setelah semua atribut berpasangan kutip dibuang, tidak boleh ada kutip tersisa di
     * dalam tag: kutip sisa berarti ada nilai atribut yang berakhir lebih awal.
     *
     * @return list<string>
     */
    private function truncatedAlpineAttributes(string $source): array
    {
        $hits = [];

        foreach ($this->tags($this->withoutBladeExpressions($source)) as $tag) {
            if (str_starts_with($tag, '<!')) {
                continue;
            }

            if (! preg_match('/(?:^|\s)(?:x-[a-z]+|@[a-z.]+)\s*=/', $tag)) {
                continue;
            }

            $sisa = (string) preg_replace([
                '/(?:^|\s)[a-zA-Z@:_][\w.:@-]*\s*=\s*"[^"]*"/',
                "/(?:^|\s)[a-zA-Z@:_][\w.:@-]*\s*=\s*'[^']*'/",
            ], ' ', $tag);

            if (str_contains($sisa, '"') || str_contains($sisa, "'")) {
                $hits[] = substr((string) preg_replace('/\s+/', ' ', trim($tag)), 0, 160);
            }
        }

        return $hits;
    }

    /** @return list<string> */
    private function opacityUtilitiesKilledByAnimation(string $source): array
    {
        $hits = [];

        foreach ($this->tags($source) as $tag) {
            if (! str_contains($tag, 'fade-in-up')) {
                continue;
            }

            if (preg_match('/opacity-[0-9]{1,3}(?![0-9])/', $tag)) {
                $hits[] = substr((string) preg_replace('/\s+/', ' ', trim($tag)), 0, 160);
            }
        }

        return $hits;
    }
}
