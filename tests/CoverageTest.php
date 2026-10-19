<?php

declare(strict_types=1);

namespace Mnml\Tests;

use Mnml\AspectRatio;
use Mnml\EnhancementKind;
use Mnml\JobStatus;
use Mnml\Mode;
use Mnml\Model;
use PHPUnit\Framework\TestCase;

/**
 * The SDK against the API's own OpenAPI document (spec/openapi.json): every field of every answer
 * is a property of its model, no more and no fewer, and the constants hold every value the API
 * takes. A field the API adds fails here until the SDK has it. scripts/update-spec.sh refreshes
 * the document.
 */
final class CoverageTest extends TestCase
{
    /** @var array<string, mixed> */
    private static array $paths;

    public static function setUpBeforeClass(): void
    {
        self::$paths = json_decode((string) file_get_contents(__DIR__ . '/../spec/openapi.json'), true)['paths'];
    }

    /** @return list<string> */
    private static function keys(array $schema): array
    {
        $keys = array_keys($schema['properties'] ?? []);
        sort($keys);

        return $keys;
    }

    private static function data(string $path, string $method): array
    {
        foreach (self::$paths[$path][$method]['responses'] as $code => $r) {
            if (str_starts_with((string) $code, '2')) {
                return $r['content']['application/json']['schema']['properties']['data'];
            }
        }
        self::fail("{$method} {$path} has no 2xx answer");
    }

    private static function prop(array $schema, string $name): array
    {
        $p = $schema['properties'][$name];
        if (isset($p['items'])) {
            return $p['items'];
        }
        foreach ($p['anyOf'] ?? [] as $a) {
            if (isset($a['properties'])) {
                return $a;
            }
        }

        return $p;
    }

    /** @param class-string $class @return list<string> */
    private static function fields(string $class): array
    {
        $out = [];
        foreach ((new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
            $out[] = strtolower((string) preg_replace('/[A-Z]/', '_$0', $p->getName()));
        }
        sort($out);

        return $out;
    }

    /** @param class-string $class @return list<string> */
    private static function constants(string $class): array
    {
        $v = array_values((new \ReflectionClass($class))->getConstants());
        sort($v);

        return $v;
    }

    private static function enum(array $schema): array
    {
        $e = $schema['enum'];
        sort($e);

        return $e;
    }

    public function testEveryAnswerFieldIsAProperty(): void
    {
        self::assertSame(self::keys(self::data('/v2/renders', 'post')), self::fields(Model\RenderStarted::class));
        self::assertSame(self::keys(self::data('/v2/edits', 'post')), self::fields(Model\JobStarted::class));
        $job = self::data('/v2/jobs/{id}', 'get');
        self::assertSame(self::keys($job), self::fields(Model\Job::class));
        self::assertSame(self::keys(self::prop($job, 'outputs')), self::fields(Model\JobOutput::class));
        self::assertSame(self::keys(self::data('/v2/jobs/{id}/cancel', 'post')), self::fields(Model\JobCanceled::class));

        $account = self::data('/v2/account', 'get');
        self::assertSame(self::keys($account), self::fields(Model\Account::class));
        self::assertSame(self::keys(self::prop($account, 'credits')), self::fields(Model\AccountCredits::class));
        self::assertSame(self::keys(self::prop($account, 'key')), self::fields(Model\AccountKey::class));
        self::assertSame(self::keys(self::prop($account, 'limits')), self::fields(Model\AccountLimits::class));

        $engines = self::data('/v2/engines', 'get');
        self::assertSame(self::keys($engines), self::fields(Model\EngineCatalog::class));
        $engine = self::prop($engines, 'engines');
        self::assertSame(self::keys($engine), self::fields(Model\Engine::class));
        self::assertSame(self::keys(self::prop($engine, 'prices')), self::fields(Model\EnginePrices::class));
        self::assertSame(self::keys(self::prop($engine, 'capabilities')), self::fields(Model\EngineCapabilities::class));
        $video = self::prop($engines, 'video_models');
        self::assertSame(self::keys($video), self::fields(Model\VideoModel::class));
        self::assertSame(self::keys(self::prop($video, 'prices')), self::fields(Model\VideoPrice::class));
    }

    public function testTheConstantsHoldEveryValueTheApiTakes(): void
    {
        $render = self::$paths['/v2/renders']['post']['requestBody']['content']['application/json']['schema']['properties'];
        self::assertSame(self::enum($render['mode']), self::constants(Mode::class));
        self::assertSame(self::enum($render['aspect_ratio']), self::constants(AspectRatio::class));
        $enhance = self::$paths['/v2/enhancements']['post']['requestBody']['content']['application/json']['schema']['properties'];
        self::assertSame(self::enum($enhance['kind']), self::constants(EnhancementKind::class));
        self::assertSame(self::enum(self::data('/v2/jobs/{id}', 'get')['properties']['status']), self::constants(JobStatus::class));
    }
}
