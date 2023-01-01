# VarDumper Extension For FFI Types

<p align="center">
    <a href="https://packagist.org/packages/ffi/var-dumper"><img src="https://poser.pugx.org/ffi/var-dumper/require/php?style=for-the-badge" alt="PHP 8.1+"></a>
    <a href="https://packagist.org/packages/ffi/var-dumper"><img src="https://poser.pugx.org/ffi/var-dumper/version?style=for-the-badge" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/ffi/var-dumper"><img src="https://poser.pugx.org/ffi/var-dumper/v/unstable?style=for-the-badge" alt="Latest Unstable Version"></a>
    <a href="https://packagist.org/packages/ffi/var-dumper"><img src="https://poser.pugx.org/ffi/var-dumper/downloads?style=for-the-badge" alt="Total Downloads"></a>
    <a href="https://raw.githubusercontent.com/php-ffi/var-dumper/master/LICENSE.md"><img src="https://poser.pugx.org/ffi/var-dumper/license?style=for-the-badge" alt="License MIT"></a>
</p>
<p align="center">
    <a href="https://github.com/php-ffi/var-dumper/actions"><img src="https://github.com/php-ffi/var-dumper/workflows/build/badge.svg"></a>
</p>

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
