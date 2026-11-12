<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Enum\Unit;
use App\Http\RequestInput;
use App\Http\UserError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RequestInputTest extends TestCase
{
    public function testFormFieldsComeBeforeTheJsonBodyAndTheQueryString(): void
    {
        $request = Request::create('/x?a=query&c=query', 'POST', ['a' => 'form'], server: ['CONTENT_TYPE' => 'application/json'], content: '{"a":"json","b":"json"}');
        $request->request->set('a', 'form');
        $input = new RequestInput($request);

        self::assertSame('form', $input->raw('a'));
        self::assertSame('json', $input->raw('b'));
        self::assertSame('query', $input->raw('c'));
        self::assertNull($input->raw('d'));
    }

    public function testTypedValues(): void
    {
        $input = $this->input(['n' => ' 12 ', 'load' => '82,5', 'unit' => 'bw', 'name' => "  Bank \t drücken ", 'notes' => " a\r\nb "]);

        self::assertSame(12, $input->int('n'));
        self::assertSame(82.5, $input->number('load'));
        self::assertSame(Unit::Bodyweight, $input->enum('unit', Unit::class));
        self::assertSame('Bank drücken', $input->string('name'));
        self::assertSame("a\nb", $input->text('notes'));
        self::assertSame('', $input->string('missing', 100, false));
    }

    #[DataProvider('invalidInput')]
    public function testInvalidInputIsA400WithAGermanMessage(callable $read, string $message): void
    {
        try {
            $read($this->input(['reps' => 'abc', 'amount' => '500', 'unit' => 'lbs', 'name' => '   ', 'list' => ['x']]));
            self::fail('Expected a UserError');
        } catch (UserError $e) {
            self::assertSame(400, $e->getStatusCode());
            self::assertSame($message, $e->getMessage());
        }
    }

    public static function invalidInput(): iterable
    {
        yield 'not a number' => [fn (RequestInput $i) => $i->int('reps'), 'Feld „reps“ muss eine ganze Zahl sein.'];
        yield 'too large' => [fn (RequestInput $i) => $i->int('amount', 1, 10), 'Feld „amount“ muss zwischen 1 und 10 liegen.'];
        yield 'array' => [fn (RequestInput $i) => $i->int('list'), 'Feld „list“ muss eine ganze Zahl sein.'];
        yield 'unknown unit' => [fn (RequestInput $i) => $i->enum('unit', Unit::class), 'Feld „unit“ hat einen ungültigen Wert.'];
        yield 'empty' => [fn (RequestInput $i) => $i->string('name'), 'Feld „name“ darf nicht leer sein.'];
        yield 'missing number' => [fn (RequestInput $i) => $i->number('load'), 'Feld „load“ muss eine Zahl sein.'];
    }

    public function testInvalidJsonIsA400(): void
    {
        $this->expectExceptionObject(UserError::badRequest('Ungültiges JSON.'));
        (new RequestInput(Request::create('/x', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{nope')))->raw('a');
    }

    private function input(array $form): RequestInput
    {
        return new RequestInput(Request::create('/x', 'POST', $form));
    }
}
