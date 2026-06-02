<?php

namespace Tests\Unit;

use App\Services\WablasService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WablasPhoneNormalizeTest extends TestCase
{
    private WablasService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WablasService::class);
    }

    #[DataProvider('phoneProvider')]
    public function test_normalize_phone(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->service->normalizePhone($input));
    }

    public static function phoneProvider(): array
    {
        return [
            'format 08' => ['0816920592', '62816920592'],
            'format 62' => ['62816920592', '62816920592'],
            'dengan spasi dan strip' => ['0816-9205-92', '62816920592'],
            'kosong' => [null, null],
            'terlalu pendek' => ['0812', null],
        ];
    }

    public function test_is_valid_phone(): void
    {
        $this->assertTrue($this->service->isValidPhone('0816920592'));
        $this->assertFalse($this->service->isValidPhone('123'));
    }
}
