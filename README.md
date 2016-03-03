# Sftp
<table border="0">
  <tr>
    <td width="310"><img height="160" width="310"alt="UCSDMath - Mathlink" src="https://github.com/ucsdmath/Testing/blob/master/ucsdmath-logo.png"></td>
    <td><h3>A Development Project in PHP</h3>
        <p><strong>UCSDMath</strong> provides a testing framework for general internal Intranet software applications for the UCSD, Department of Mathematics. This is used for development and testing only. [not for production]</p>
    </td>
  </tr>
</table>
---
[![Build Status](https://scrutinizer-ci.com/g/ucsdmath/Sftp/badges/build.png?b=master)](https://scrutinizer-ci.com/g/ucsdmath/Sftp/build-status/master)
[![Latest Stable Version](https://poser.pugx.org/ucsdmath/Sftp/v/stable)](https://packagist.org/packages/ucsdmath/Sftp)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.0-8892BF.svg?style=flat)](https://php.net/)
[![Total Downloads](https://poser.pugx.org/ucsdmath/Sftp/downloads)](https://packagist.org/packages/ucsdmath/Sftp)
[![Latest Unstable Version](https://poser.pugx.org/ucsdmath/Sftp/v/unstable)](https://packagist.org/packages/ucsdmath/Sftp)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/ucsdmath/Sftp/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/ucsdmath/Sftp/?branch=master)
[![License](https://poser.pugx.org/ucsdmath/Sftp/license)](https://packagist.org/packages/ucsdmath/Sftp)

Sftp is a testing and development library only. This is not to be used in a production.

## Installation using [Composer](http://getcomposer.org/)
You can install the class ```Sftp``` with Composer and Packagist by
adding the ucsdmath/sftp package to your composer.json file:

```
"require": {
    "php": "^7.0",
    "ucsdmath/sftp": "dev-master"
},
```
Or you can add the class directly from the terminal prompt:

```bash
$ composer require ucsdmath/sftp
```

## Usage

``` php
$sftp = new \UCSDMath\Sftp\Sftp();
```

## Documentation

No documentation site available at this time.
<!-- [Check out the documentation](http://math.ucsd.edu/~deisner/documentation/Sftp/) -->

## Testing

``` bash
$ phpunit
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email deisner@ucsd.edu instead of using the issue tracker.

## Credits

- [Daryl Eisner](https://github.com/UCSDMath)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
