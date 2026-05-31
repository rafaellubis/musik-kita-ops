<?php

namespace Database\Seeders;

use App\Models\Instrument;
use App\Models\ReportTemplate;
use App\Models\ReportTemplateItem;
use App\Models\ReportTemplateSection;
use Illuminate\Database\Seeder;

/**
 * Seed template laporan progres bulanan per instrumen & tipe paket.
 * Reguler + Hobby (7 instrumen incl. Saxophone) + Kids Class. DUO pakai template Reguler + seksi Berduo.
 */
class ReportTemplateSeeder extends Seeder
{
    /** Item sikap belajar — sama di semua template privat. */
    private const SIKAP_ITEMS = [
        'Hadir dan tepat waktu di kelas',
        'Latihan mandiri di rumah sesuai arahan guru',
        'Mengerjakan tugas/latihan yang diberikan guru',
        'Terbuka menerima koreksi dan semangat belajar',
    ];

    /** Seksi khusus paket DUO (Reguler Basic, instrumen sama, 1 ruangan). */
    private const DUO_SECTION = [
        'title' => 'Belajar Berduo (Paket DUO)',
        'items' => [
            'Saling menghormati saat bergantian latihan',
            'Bisa ikut tempo/ketukan bersama partner',
            'Bergantian main dan mendengarkan partner dengan sabar',
            'Kooperatif saat guru memberi arahan ke keduanya',
            'Saling mendukung (adik-kakak / teman sekelas)',
        ],
    ];

    public function run(): void
    {
        $instruments = Instrument::pluck('id', 'code');

        $sort = 1;

        foreach ($this->regulerTemplates() as $code => $sections) {
            if (! isset($instruments[$code])) {
                continue;
            }
            $this->seedTemplate(
                instrumentId: $instruments[$code],
                name: $this->instrumentLabel($code) . ' · Reguler',
                templateKind: ReportTemplate::KIND_REGULER,
                description: 'Checklist bulanan Reguler (Basic–L4). Centang item sesuai level murid. Paket DUO gunakan seksi Belajar Berduo.',
                sortOrder: $sort++,
                sections: array_merge($sections, [$this->duoSection(), $this->sikapSection()])
            );
        }

        foreach ($this->hobbyTemplates() as $code => $sections) {
            if (! isset($instruments[$code])) {
                continue;
            }
            $this->seedTemplate(
                instrumentId: $instruments[$code],
                name: $this->instrumentLabel($code) . ' · Hobby',
                templateKind: ReportTemplate::KIND_HOBBY,
                description: 'Checklist bulanan Hobby — fokus lagu, ritme, dan enjoyment.',
                sortOrder: $sort++,
                sections: array_merge($sections, [$this->sikapSection()])
            );
        }

        if (isset($instruments['KIDS'])) {
            $this->seedTemplate(
                instrumentId: $instruments['KIDS'],
                name: 'Kids Class · Eksplorasi Bakat',
                templateKind: ReportTemplate::KIND_KIDS,
                description: 'Laporan bulanan anak usia 4–5 tahun — eksplorasi berbagai alat musik & vokal.',
                sortOrder: $sort++,
                sections: $this->kidsSections()
            );
        }
    }

    private function instrumentLabel(string $code): string
    {
        return match ($code) {
            'PIANO'  => 'Piano',
            'GITAR'  => 'Gitar',
            'DRUM'   => 'Drum',
            'VOCAL'  => 'Vocal',
            'BASS'   => 'Bass',
            'VIOLIN' => 'Violin',
            'SAX'    => 'Saxophone',
            default  => $code,
        };
    }

    private function sikapSection(): array
    {
        return [
            'title' => 'Sikap & Kedisiplinan Belajar',
            'items' => self::SIKAP_ITEMS,
        ];
    }

    private function duoSection(): array
    {
        return self::DUO_SECTION;
    }

    /**
     * @param  array<int, array{title: string, items: array<int, string>}>  $sections
     */
    private function seedTemplate(
        int $instrumentId,
        string $name,
        string $templateKind,
        string $description,
        int $sortOrder,
        array $sections,
    ): void {
        $template = ReportTemplate::firstOrCreate(
            ['instrument_id' => $instrumentId, 'name' => $name],
            [
                'template_kind' => $templateKind,
                'description'   => $description,
                'is_active'     => true,
                'sort_order'    => $sortOrder,
            ]
        );

        $template->update([
            'template_kind' => $templateKind,
            'description'   => $description,
            'is_active'     => true,
            'sort_order'    => $sortOrder,
        ]);

        $sectionOrder = 1;
        foreach ($sections as $sectionData) {
            $section = ReportTemplateSection::firstOrCreate(
                [
                    'report_template_id' => $template->id,
                    'title'              => $sectionData['title'],
                ],
                ['sort_order' => $sectionOrder]
            );

            if ($section->sort_order !== $sectionOrder) {
                $section->update(['sort_order' => $sectionOrder]);
            }

            $itemOrder = 1;
            foreach ($sectionData['items'] as $label) {
                $item = ReportTemplateItem::firstOrCreate(
                    [
                        'report_template_section_id' => $section->id,
                        'label'                      => $label,
                    ],
                    ['sort_order' => $itemOrder]
                );

                if ($item->sort_order !== $itemOrder) {
                    $item->update(['sort_order' => $itemOrder]);
                }

                $itemOrder++;
            }

            $sectionOrder++;
        }
    }

    /** @return array<string, array<int, array{title: string, items: array<int, string>}>> */
    private function regulerTemplates(): array
    {
        return [
            'PIANO' => [
                [
                    'title' => 'Postur & Teknik Jari',
                    'items' => [
                        'Duduk & posisi tangan/postur tubuh sudah benar',
                        'Jari lengkung (busur jari) konsisten saat menekan tuts',
                        'Pergantian jari (fingerings) dasar sudah mulai terbiasa',
                        'Koordinasi tangan kanan & kiri (sesuai level grade)',
                        'Tekanan tuts jelas — tidak menabrak not lain',
                        'Penggunaan pedal sustain — untuk Level 2 ke atas',
                    ],
                ],
                [
                    'title' => 'Membaca Not & Teori',
                    'items' => [
                        'Mengenal posisi not di grand staff (sesuai grade)',
                        'Membaca ritme dasar (separuh, sepertiga, dll.)',
                        'Mengenal tanda birama & ketukan',
                        'Skala/tangga nada mayor dasar (sesuai grade)',
                        'Interval & akor dasar — Level 2 ke atas',
                        'Dinamika (keras/lembut) dan simbol musik dasar',
                    ],
                ],
                [
                    'title' => 'Repertoar & Musikalitas',
                    'items' => [
                        'Minimal 1 lagu/potongan dikuasai sampai akhir (sesuai grade)',
                        'Tempo stabil dengan metronom atau iringan',
                        'Ekspresi: dinamika dan perasaan lagu mulai terdengar',
                        'Siap tampil atau ujian internal (jika mendekati event)',
                    ],
                ],
            ],
            'GITAR' => [
                [
                    'title' => 'Postur & Teknik',
                    'items' => [
                        'Postur duduk/berdiri & posisi gitar benar',
                        'Teknik petik (jari/pick) konsisten',
                        'Teknik fret: tekan senar jernih tanpa buzz',
                        'Pergantian posisi tangan di fretboard (sesuai grade)',
                        'Hammer-on / pull-off / slide — Level 2 ke atas',
                    ],
                ],
                [
                    'title' => 'Not, Tab & Teori',
                    'items' => [
                        'Membaca tabulasi',
                        'Membaca chord chart',
                        'Membaca not balok treble (sesuai grade Reguler)',
                        'Skala & interval dasar',
                        'Progression akor umum — Level 2 ke atas',
                    ],
                ],
                [
                    'title' => 'Repertoar & Musikalitas',
                    'items' => [
                        'Lagu/potongan dikuasai sesuai grade',
                        'Strumming/fingerstyle pattern stabil',
                        'Main bersama metronom/playback',
                        'Ekspresi dinamika & feel lagu',
                    ],
                ],
            ],
            'DRUM' => [
                [
                    'title' => 'Postur & Teknik Tangan-Kaki',
                    'items' => [
                        'Postur duduk & grip stick benar',
                        'Teknik rebound stroke konsisten',
                        'Koordinasi tangan-kaki (hi-hat + kick)',
                        'Kontrol dinamika: ghost note & accent',
                    ],
                ],
                [
                    'title' => 'Rudiments & Koordinasi',
                    'items' => [
                        'Single stroke roll dasar',
                        'Double stroke / paradiddle dasar — Level 1 ke atas',
                        'Fill drum singkat yang musikal',
                        'Independence exercises (sesuai grade)',
                    ],
                ],
                [
                    'title' => 'Groove, Tempo & Repertoar',
                    'items' => [
                        'Groove dasar (rock, pop, ballad) stabil',
                        'Main dengan metronom / backing track',
                        'Transisi antar bagian lagu (verse–chorus)',
                        'Ketahanan tempo — main terus tanpa tergesa',
                    ],
                ],
            ],
            'VOCAL' => [
                [
                    'title' => 'Teknik Vokal Dasar',
                    'items' => [
                        'Pernafasan perut (diafragma)',
                        'Pemanasan suara (warm-up) rutin',
                        'Intonasi/nada stabil',
                        'Artikulasi & pelafalan jelas',
                        'Register chest/head voice — Level 2 ke atas',
                        'Teknik falsetto / mix — Level 3 ke atas',
                    ],
                ],
                [
                    'title' => 'Teori & Pendengaran',
                    'items' => [
                        'Mengenal interval nada dasar',
                        'Membaca not melodi sederhana',
                        'Latihan pendengaran: meniru nada/melodi',
                        'Dinamika & frase musikal',
                    ],
                ],
                [
                    'title' => 'Repertoar & Ekspresi',
                    'items' => [
                        'Lagu dikuasai dengan nada stabil',
                        'Ekspresi emosi & dinamika',
                        'Teknik microphone (jika sudah diajarkan)',
                        'Kesiapan tampil',
                    ],
                ],
            ],
            'BASS' => [
                [
                    'title' => 'Teknik & Postur',
                    'items' => [
                        'Postur & posisi tangan benar',
                        'Teknik jari / pick konsisten',
                        'Freting jernih tanpa buzz',
                        'Perpindahan posisi di neck lancar',
                    ],
                ],
                [
                    'title' => 'Groove, Timing & Teori',
                    'items' => [
                        'Lock dengan metronom/drum machine',
                        'Root note & pola bassline dasar',
                        'Walking bass / pattern lanjutan — Level 2 ke atas',
                        'Membaca chart/tab (Reguler)',
                    ],
                ],
                [
                    'title' => 'Repertoar & Feel',
                    'items' => [
                        'Main iringan 1 lagu utuh',
                        'Feel genre (pop, rock, funk)',
                        'Kontrol dinamika & muting',
                    ],
                ],
            ],
            'VIOLIN' => [
                [
                    'title' => 'Postur & Bowing',
                    'items' => [
                        'Postur berdiri/duduk & pegang biola benar',
                        'Pegang bow & gerakan bow dasar',
                        'Bow paralel & sonoritas jernih',
                        'Koordinasi bow + jari kiri',
                    ],
                ],
                [
                    'title' => 'Intonasi & Teknik Jari',
                    'items' => [
                        'Intonasi (nada akurat) di posisi dasar',
                        'Pergantian posisi jari lancar',
                        'Vibrato dasar — Level 3 ke atas',
                        'Membaca not violin (Reguler)',
                    ],
                ],
                [
                    'title' => 'Repertoar & Musikalitas',
                    'items' => [
                        'Lagu/étude dikuasai sesuai level',
                        'Artikulasi: legato, staccato dasar',
                        'Ekspresi dinamika',
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, array<int, array{title: string, items: array<int, string>}>> */
    private function hobbyTemplates(): array
    {
        return [
            'PIANO' => [
                [
                    'title' => 'Dasar Bermain',
                    'items' => [
                        'Posisi duduk & tangan nyaman',
                        'Bisa main melodi lagu pilihan (minimal 1 lagu)',
                        'Akor dasar / iringan sederhana (jika sudah diajarkan)',
                        'Transisi antar bagian lagu mulai lancar',
                    ],
                ],
                [
                    'title' => 'Ritme & Feel',
                    'items' => [
                        'Ikut ketukan/tempo dengan stabil',
                        'Pola iringan dasar stabil',
                        'Feel lagu (ballad, pop groove) mulai terasa',
                    ],
                ],
                [
                    'title' => 'Ekspresi & Semangat Belajar',
                    'items' => [
                        'Berani main dengan ekspresi, tidak kaku',
                        'Menikmati proses belajar di kelas',
                        'Siap share/recording sederhana untuk orang tua',
                    ],
                ],
            ],
            'GITAR' => [
                [
                    'title' => 'Chord & Lagu',
                    'items' => [
                        'Minimal 3–5 chord dasar dikuasai',
                        'Ganti chord dengan mulus',
                        'Main 1 lagu utuh (verse + chorus)',
                        'Iringan teman nyanyi / main bareng',
                    ],
                ],
                [
                    'title' => 'Ritme & Picking',
                    'items' => [
                        'Pola strumming stabil',
                        'Picking dasar / arpeggio sederhana',
                        'Tempo sesuai lagu',
                    ],
                ],
                [
                    'title' => 'Ekspresi',
                    'items' => [
                        'Feel lagu (rock, pop, ballad)',
                        'Berani main di depan keluarga/teman',
                    ],
                ],
            ],
            'DRUM' => [
                [
                    'title' => 'Groove & Feel',
                    'items' => [
                        'Beat dasar lagu pop/rock stabil',
                        'Fill sederhana tanpa mengganggu tempo',
                        'Main iringan teman main gitar/vokal',
                    ],
                ],
                [
                    'title' => 'Koordinasi',
                    'items' => [
                        'Hi-hat + snare + kick sinkron',
                        'Variasi dinamis (keras/lembut)',
                    ],
                ],
                [
                    'title' => 'Repertoar',
                    'items' => [
                        'Minimal 1–2 lagu dikuasai dari awal sampai akhir',
                    ],
                ],
            ],
            'VOCAL' => [
                [
                    'title' => 'Kemampuan Bernyanyi',
                    'items' => [
                        'Pernafasan perut saat bernyanyi',
                        'Nada stabil di lagu pilihan',
                        'Ekspresi & dinamika (keras/lembut)',
                        'Teknik lanjutan (falsetto/vibrato) — jika sudah diajarkan',
                    ],
                ],
                [
                    'title' => 'Teori Musik (ringan)',
                    'items' => [
                        'Mengenal not/melodi dasar',
                        'Ritme & ketukan saat bernyanyi',
                        'Tangga nada dasar',
                    ],
                ],
                [
                    'title' => 'Penampilan & Kepercayaan Diri',
                    'items' => [
                        'Percaya diri bernyanyi di kelas',
                        'Ekspresi panggung dasar',
                        'Siap tampil di depan keluarga/teman',
                    ],
                ],
            ],
            'BASS' => [
                [
                    'title' => 'Groove & Lagu',
                    'items' => [
                        'Pola bassline dasar stabil',
                        'Main iringan 1 lagu utuh',
                        'Lock tempo dengan metronom',
                    ],
                ],
                [
                    'title' => 'Teknik Dasar',
                    'items' => [
                        'Postur & fretting jernih',
                        'Transisi antar posisi mulus',
                    ],
                ],
                [
                    'title' => 'Feel & Ekspresi',
                    'items' => [
                        'Feel genre pop/rock/funk',
                        'Siap main bareng teman/guru',
                    ],
                ],
            ],
            'VIOLIN' => [
                [
                    'title' => 'Dasar Bermain',
                    'items' => [
                        'Postur & pegang biola/bow benar',
                        'Nada dasar jernih di senar',
                        'Gerakan bow mulai konsisten',
                    ],
                ],
                [
                    'title' => 'Repertoar',
                    'items' => [
                        'Minimal 1 potongan/lagu sederhana dikuasai',
                        'Tempo stabil saat latihan',
                    ],
                ],
                [
                    'title' => 'Ekspresi',
                    'items' => [
                        'Ekspresi dinamika dasar',
                        'Semangat & enjoy belajar biola',
                    ],
                ],
            ],
            'SAX' => [
                [
                    'title' => 'Postur, Embouchure & Pernafasan',
                    'items' => [
                        'Postur duduk/berdiri & pegang saxophone benar',
                        'Embouchure (cara meniup mouthpiece) mulai stabil',
                        'Pernafasan perut saat meniup',
                        'Suara/nada pertama jernih (minimal squawk)',
                    ],
                ],
                [
                    'title' => 'Teknik Jari & Ritme',
                    'items' => [
                        'Posisi jari dasar menghasilkan nada jernih',
                        'Pergantian jari antar not mulai lancar',
                        'Ikut ketukan/tempo dengan metronom',
                        'Artikulasi tonguing dasar (tah-tah)',
                    ],
                ],
                [
                    'title' => 'Repertoar & Musikalitas',
                    'items' => [
                        'Minimal 1 lagu/melodi dikuasai',
                        'Dinamika dasar (keras/lembut)',
                        'Feel lagu (jazz, pop, ballad) mulai terasa',
                        'Siap main iringan atau recording sederhana untuk orang tua',
                    ],
                ],
            ],
        ];
    }

    /** @return array<int, array{title: string, items: array<int, string>}> */
    private function kidsSections(): array
    {
        return [
            [
                'title' => 'Eksplorasi Alat Musik',
                'items' => [
                    'Sudah mencoba minimal 2 jenis alat musik di kelas',
                    'Berani menyentuh/mencoba alat dengan bimbingan guru',
                    'Menunjukkan minat kuat ke alat tertentu (catat di ringkasan seksi)',
                ],
            ],
            [
                'title' => 'Ritme & Gerak',
                'items' => [
                    'Ikut tepuk tangan / gerak ritme sederhana',
                    'Koordinasi gerak badan + suara',
                    'Mengenal konsep nada tinggi & rendah',
                ],
            ],
            [
                'title' => 'Vokal & Ekspresi',
                'items' => [
                    'Ikut bernyanyi lagu kelas',
                    'Berani bersuara meski malu-malu',
                    'Ekspresi wajah & gerak saat musik',
                ],
            ],
            [
                'title' => 'Sosial & Partisipasi Grup',
                'items' => [
                    'Interaksi positif dengan teman sekelas',
                    'Menunggu giliran & mengikuti instruksi guru',
                    'Antusiasme masuk kelas',
                ],
            ],
            [
                'title' => 'Minat & Bakat (Wajib diisi guru)',
                'items' => [
                    'Anak nyaman & enjoy di kelas',
                    'Orang tua mendampingi latihan singkat di rumah (jika dianjurkan)',
                    'Indikasi minat ke instrumen/vokal tertentu sudah terlihat',
                ],
            ],
        ];
    }
}
