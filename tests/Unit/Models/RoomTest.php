<?php

namespace Tests\Unit\Models;

use App\Models\Room;
use Tests\TestCase;

class RoomTest extends TestCase
{
    public function test_supports_instrument_returns_true_when_in_list(): void
    {
        $room = new Room();
        $room->supported_instruments = ['Piano', 'Gitar'];

        $this->assertTrue($room->supportsInstrument('Piano'));
        $this->assertTrue($room->supportsInstrument('Gitar'));
    }

    public function test_supports_instrument_returns_false_when_not_in_list(): void
    {
        $room = new Room();
        $room->supported_instruments = ['Piano'];

        $this->assertFalse($room->supportsInstrument('Drum'));
    }

    public function test_supports_instrument_returns_false_when_list_empty(): void
    {
        $room = new Room();
        $room->supported_instruments = [];

        $this->assertFalse($room->supportsInstrument('Piano'));
    }

    public function test_supports_instrument_returns_false_when_null(): void
    {
        $room = new Room();
        $room->supported_instruments = null;

        $this->assertFalse($room->supportsInstrument('Piano'));
    }
}
