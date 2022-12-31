# VarDumper Extension For FFI Types

This library allows you to dump FFI types using the functions `dd()` and `dump()`.

## Requirements

- PHP >= 8.1

## Installation

Library is available as composer repository and can be installed using the
following command in a root of your project.

```sh
$ composer require ffi/var-dumper
```

## Usage

```php
dump(\FFI::new('struct { float x }'));

//
// Expected Output:
//
// struct <anonymous> {
//   x<float>: 0.0
// }
//
```

### Unsafe Access

Some values may contain data that will cause access errors when read. For 
example, pointers leading to "emptiness".

Such data is marked as "unsafe" and only the first element is displayed. If you
want to display the values in full, you should use the `VAR_DUMPER_FFI_UNSAFE=1`
environment variable.

```php
// Create char* with "Hello World!\0" string.
$string = \FFI::new('char[13]');
\FFI::memcpy($string, 'Hello World!', 12);
$pointer = \FFI::cast('char*', $string);

// Dump
dump($pointer);

// VAR_DUMPER_FFI_UNSAFE=0
//
// > char* (unsafe access) {
// >  +0: "H"
// > }

// VAR_DUMPER_FFI_UNSAFE=1
//
// > b"Hello World!\x00"
```
