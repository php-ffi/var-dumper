<?php

declare(strict_types=1);

namespace FFI\VarDumper\Tests;

use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

final class FFICasterTestCase extends TestCase
{
    use VarDumperTestTrait;

    protected function setUp(): void
    {
        $_SERVER['VAR_DUMPER_FFI_UNSAFE'] = 0;

        if (\in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)
            && \ini_get('ffi.enable') === 'preload') {
            return;
        }

        if (!filter_var(\ini_get('ffi.enable'), \FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('FFI not enabled for CLI SAPI');
        }
    }

    public function testCastAnonymousStruct()
    {
        $this->assertDumpEquals(<<<'PHP'
        struct <anonymous> {
          x<uint32_t>: 0
        }
        PHP, \FFI::new('struct { uint32_t x; }'));
    }

    public function testCastNamedStruct()
    {
        $this->assertDumpEquals(<<<'PHP'
        struct Example {
          x<uint32_t>: 0
        }
        PHP, \FFI::new('struct Example { uint32_t x; }'));
    }

    public function testCastAnonymousUnion()
    {
        $this->assertDumpEquals(<<<'PHP'
        union <anonymous> {
          x<uint32_t>: 0
          y<uint32_t>: 0
        }
        PHP, \FFI::new('union { uint32_t x; uint32_t y; }'));
    }

    public function testCastNamedUnion()
    {
        $this->assertDumpEquals(<<<'PHP'
        union Example {
          x<uint32_t>: 0
          y<uint32_t>: 0
        }
        PHP, \FFI::new('union Example { uint32_t x; uint32_t y; }'));
    }

    public function testCastAnonymousEnum()
    {
        $this->assertDumpEquals(<<<'PHP'
        enum <anonymous> {
          cdata: 0
        }
        PHP, \FFI::new('enum { a, b }'));
    }

    public function testCastNamedEnum()
    {
        $this->assertDumpEquals(<<<'PHP'
        enum Example {
          cdata: 0
        }
        PHP, \FFI::new('enum Example { a, b }'));
    }

    public function scalarsDataProvider(): array
    {
        return [
            'int8_t' => ['int8_t', '0', 1, 1],
            'uint8_t' => ['uint8_t', '0', 1, 1],
            'int16_t' => ['int16_t', '0', 2, 2],
            'uint16_t' => ['uint16_t', '0', 2, 2],
            'int32_t' => ['int32_t', '0', 4, 4],
            'uint32_t' => ['uint32_t', '0', 4, 4],
            'int64_t' => ['int64_t', '0', 8, 8],
            'uint64_t' => ['uint64_t', '0', 8, 8],

            'bool' => ['bool', 'false', 1, 1],
            'char' => ['char', '"\x00"', 1, 1],
            'float' => ['float', '0.0', 4, 4],
            'double' => ['double', '0.0', 8, 8],
        ];
    }

    /**
     * @dataProvider scalarsDataProvider
     */
    public function testCastScalar(string $type, string $value, int $size, int $align)
    {
        $this->assertDumpEquals(<<<PHP
        $type {
          cdata: $value
        }
        PHP, \FFI::new($type));
    }

    public function testCastVoidFunction()
    {
        $abi = \PHP_OS_FAMILY === 'Windows' ? '[cdecl]' : '[fastcall]';

        $this->assertDumpEquals(<<<PHP
        $abi callable(): void {
          returnType: FFI\CType<void> {}
        }
        PHP, \FFI::new('void (*)(void)'));
    }

    public function testCastIntFunction()
    {
        $abi = \PHP_OS_FAMILY === 'Windows' ? '[cdecl]' : '[fastcall]';

        $this->assertDumpEquals(<<<PHP
        $abi callable(): uint64_t {
          returnType: FFI\CType<uint64_t> {}
        }
        PHP, \FFI::new('unsigned long long (*)(void)'));
    }

    public function testCastFunctionWithArguments()
    {
        $abi = \PHP_OS_FAMILY === 'Windows' ? '[cdecl]' : '[fastcall]';

        $this->assertDumpEquals(<<<PHP
        $abi callable(int32_t, char*): void {
          returnType: FFI\CType<void> {}
        }
        PHP, \FFI::new('void (*)(int a, const char* b)'));
    }

    public function testCastNonCuttedPointerToChar()
    {
        $actualMessage = "Hello World!\0";

        $string = \FFI::new('char[100]');
        $pointer = \FFI::addr($string[0]);
        \FFI::memcpy($pointer, $actualMessage, \strlen($actualMessage));

        $this->assertDumpEquals(<<<'PHP'
        char* (unsafe access) {
          +0: "H"
        }
        PHP, $pointer);
    }

    public function testCastNonCuttedPointerToCharWithUnsafeAccess()
    {
        $actualMessage = "Hello World!\0";

        $string = \FFI::new('char[100]');
        $pointer = \FFI::addr($string[0]);
        \FFI::memcpy($pointer, $actualMessage, \strlen($actualMessage));

        $_SERVER['VAR_DUMPER_FFI_UNSAFE'] = 1;
        $this->assertDumpEquals('b"Hello World!\x00"', $pointer);
    }

    /**
     * It is worth noting that such a test can cause SIGSEGV, as it breaks
     * into "foreign" memory. However, this is only theoretical, since
     * memory is allocated within the PHP process and almost always "garbage
     * data" will be read from the PHP process itself.
     *
     * If this test fails for some reason, please report it: We may have to
     * disable the dumping of strings ("char*") feature in VarDumper.
     *
     * @see FFICaster::castFFIStringValue()
     */
    public function testCastNonTrailingCharPointer()
    {
        $actualMessage = 'Hello World!';
        $actualLength = \strlen($actualMessage);

        $string = \FFI::new('char['.$actualLength.']');
        $pointer = \FFI::addr($string[0]);

        \FFI::memcpy($pointer, $actualMessage, $actualLength);

        // Remove automatically addition of the trailing "\0" and remove trailing "\0"
        $pointer = \FFI::cast('char*', \FFI::cast('void*', $pointer));
        $pointer[$actualLength] = "\x01";

        $this->assertDumpMatchesFormat(<<<'PHP'
        char* (unsafe access) {
          +0: "H"
        }
        PHP, $pointer);
    }

    public function testCastUnionWithDirectReferencedFields()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef union Event {
            int32_t x;
            float y;
        } Event;
        CPP);

        $this->assertDumpEquals(<<<'OUTPUT'
        union Event {
          x<int32_t>: 0
          y<float>: 0.0
        }
        OUTPUT, $ffi->new('Event'));
    }

    public function testCastUnionWithPointerReferencedFields()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef union Event {
            void* something;
            char* string;
        } Event;
        CPP);

        $this->assertDumpEquals(<<<'OUTPUT'
        union Event {
          something<void*>: null
          string<char*>: null
        }
        OUTPUT, $ffi->new('Event'));
    }

    public function testCastUnionWithMixedFields()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef union Event {
            void* a;
            int32_t b;
            char* c;
            ptrdiff_t d;
        } Event;
        CPP);

        $this->assertDumpEquals(<<<'OUTPUT'
        union Event {
          a<void*>: null
          b<int32_t>: 0
          c<char*>: null
          d<int64_t>: 0
        }
        OUTPUT, $ffi->new('Event'));
    }

    public function testCastPointerToEmptyScalars()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef struct {
            int8_t *a;
            uint8_t *b;
            int64_t *c;
            uint64_t *d;
            float *e;
            double *f;
            bool *g;
        } Example;
        CPP);

        $this->assertDumpEquals(<<<'OUTPUT'
        struct <anonymous> {
          a<int8_t*>: null
          b<uint8_t*>: null
          c<int64_t*>: null
          d<uint64_t*>: null
          e<float*>: null
          f<double*>: null
          g<bool*>: null
        }
        OUTPUT, $ffi->new('Example'));
    }

    public function testCastPointerToNonEmptyScalars()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef struct {
            int8_t *a;
            uint8_t *b;
            int64_t *c;
            uint64_t *d;
            float *e;
            double *f;
            bool *g;
        } Example;
        CPP);

        // Create values
        $int = \FFI::new('int64_t');
        $int->cdata = 42;
        $float = \FFI::new('float');
        $float->cdata = 42.0;
        $double = \FFI::new('double');
        $double->cdata = 42.2;
        $bool = \FFI::new('bool');
        $bool->cdata = true;

        // Fill struct
        $struct = $ffi->new('Example');
        $struct->a = \FFI::addr(\FFI::cast('int8_t', $int));
        $struct->b = \FFI::addr(\FFI::cast('uint8_t', $int));
        $struct->c = \FFI::addr(\FFI::cast('int64_t', $int));
        $struct->d = \FFI::addr(\FFI::cast('uint64_t', $int));
        $struct->e = \FFI::addr(\FFI::cast('float', $float));
        $struct->f = \FFI::addr(\FFI::cast('double', $double));
        $struct->g = \FFI::addr(\FFI::cast('bool', $bool));

        $this->assertDumpEquals(<<<'OUTPUT'
        struct <anonymous> {
          a<int8_t*>: int8_t* {
            +0: 42
          }
          b<uint8_t*>: uint8_t* {
            +0: 42
          }
          c<int64_t*>: int64_t* {
            +0: 42
          }
          d<uint64_t*>: uint64_t* {
            +0: 42
          }
          e<float*>: float* {
            +0: 42.0
          }
          f<double*>: double* {
            +0: 42.2
          }
          g<bool*>: bool* {
            +0: true
          }
        }
        OUTPUT, $struct);
    }

    public function testCastPointerToStruct()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef struct {
            int8_t a;
        } Example;
        CPP);

        $struct = $ffi->new('Example', false);

        $this->assertDumpEquals(<<<'OUTPUT'
        struct <anonymous>* {
          a<int8_t>: 0
        }
        OUTPUT, \FFI::addr($struct));

        // Save the pointer as variable so that
        // it is not cleaned up by the GC
        $pointer = \FFI::addr($struct);

        $this->assertDumpEquals(<<<'OUTPUT'
        struct <anonymous>** {
          +0: struct <anonymous>* {
            a<int8_t>: 0
          }
        }
        OUTPUT, \FFI::addr($pointer));

        \FFI::free($struct);
    }

    public function testCastComplexType()
    {
        $ffi = \FFI::cdef(<<<'CPP'
        typedef struct {
            int x;
            int y;
        } Point;
        typedef struct Example {
            uint8_t a[8];
            long b;
            __extension__ union {
                __extension__ struct {
                    short c;
                    long d;
                };
                struct {
                    Point point;
                    float e;
                };
            };
            short f;
            bool g;
            int (*func)(
                struct __sub *h
            );
        } Example;
        CPP);

        $var = $ffi->new('Example');
        $var->func = (static fn (object $p) => 42);

        $abi = \PHP_OS_FAMILY === 'Windows' ? '[cdecl]' : '[fastcall]';
        $longSize = \FFI::type('long')->getSize();
        $longType = 8 === $longSize ? 'int64_t' : 'int32_t';
        $structSize = 56 + $longSize * 2;

        $this->assertDumpEquals(<<<OUTPUT
        struct Example {
          a<uint8_t[8]>: array:uint8_t[8] [
            0 => 0
            1 => 0
            2 => 0
            3 => 0
            4 => 0
            5 => 0
            6 => 0
            7 => 0
          ]
          b<int32_t>: 0
          c<int16_t>: 0
          d<int32_t>: 0
          point: struct <anonymous> {
            x<int32_t>: 0
            y<int32_t>: 0
          }
          e<float>: 0.0
          f<int16_t>: 0
          g<bool>: false
          func<int32_t(*)()>: [cdecl] callable(struct __sub*): int32_t {
            returnType: FFI\CType<int32_t> {}
          }
        }
        OUTPUT, $var);
    }
}
