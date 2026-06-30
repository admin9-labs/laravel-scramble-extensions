<?php

namespace Admin9\ScrambleExtensions\Tests;

use Admin9\ScrambleExtensions\Extensions\BusinessResponseInferExtension;
use Admin9\ScrambleExtensions\Tests\Fixtures\Controllers\UserController;
use Dedoc\Scramble\Infer\Contracts\ArgumentTypeBag;
use Dedoc\Scramble\Infer\Extensions\Event\MethodCallEvent;
use Dedoc\Scramble\Infer\Scope\GlobalScope;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Literal\LiteralIntegerType;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\StringType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\UnknownType;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

class BusinessResponseInferExtensionTest extends TestCase
{
    public function test_wraps_success_data_in_200_json_response_type(): void
    {
        $type = $this->extension()->getMethodReturnType($this->methodCall('success', [
            0 => new StringType,
        ]));

        $this->assertJsonResponseType($type, Response::HTTP_OK);
        $this->assertInstanceOf(StringType::class, $type->templateTypes[0]);
    }

    #[DataProvider('errorStatuses')]
    public function test_documents_supported_error_statuses(int $status): void
    {
        $type = $this->extension()->getMethodReturnType($this->methodCall('error', [
            1 => new LiteralIntegerType($status),
        ]));

        $this->assertJsonResponseType($type, $status);
        $this->assertInstanceOf(ObjectType::class, $type->templateTypes[0]);
    }

    public function test_documents_deny_as_forbidden_status(): void
    {
        $type = $this->extension()->getMethodReturnType($this->methodCall('deny'));

        $this->assertJsonResponseType($type, Response::HTTP_FORBIDDEN);
        $this->assertInstanceOf(ObjectType::class, $type->templateTypes[0]);
    }

    /**
     * @return array<string, array{status: int}>
     */
    public static function errorStatuses(): array
    {
        return [
            '401' => ['status' => Response::HTTP_UNAUTHORIZED],
            '403' => ['status' => Response::HTTP_FORBIDDEN],
            '404' => ['status' => Response::HTTP_NOT_FOUND],
            '413' => ['status' => Response::HTTP_REQUEST_ENTITY_TOO_LARGE],
            '422' => ['status' => Response::HTTP_UNPROCESSABLE_ENTITY],
        ];
    }

    /**
     * @param  array<int|string, Type>  $arguments
     */
    private function methodCall(string $method, array $arguments = []): MethodCallEvent
    {
        return new MethodCallEvent(
            new ObjectType(UserController::class),
            $method,
            new GlobalScope,
            new ArrayArgumentTypeBag($arguments),
            UserController::class,
        );
    }

    private function extension(): BusinessResponseInferExtension
    {
        return new BusinessResponseInferExtension;
    }

    private function assertJsonResponseType(?Type $type, int $status): void
    {
        $this->assertInstanceOf(Generic::class, $type);
        $this->assertSame(JsonResponse::class, $type->name);
        $this->assertCount(3, $type->templateTypes);
        $this->assertInstanceOf(LiteralIntegerType::class, $type->templateTypes[1]);
        $this->assertSame($status, $type->templateTypes[1]->value);
    }
}

/**
 * @internal
 */
final class ArrayArgumentTypeBag implements ArgumentTypeBag
{
    /**
     * @param  array<int|string, Type>  $arguments
     */
    public function __construct(private array $arguments) {}

    public function get(string $name, int $position, ?Type $default = new UnknownType): ?Type
    {
        return $this->arguments[$name] ?? $this->arguments[$position] ?? $default;
    }

    /**
     * @return array<int|string, Type>
     */
    public function all(): array
    {
        return $this->arguments;
    }

    public function map(callable $cb): self
    {
        return new self(array_map($cb, $this->arguments, array_keys($this->arguments)));
    }

    public function count(): int
    {
        return count($this->arguments);
    }
}
