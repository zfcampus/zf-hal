# CONTRIBUTING

Apigility and related modules (of which this is one) are open source and licensed
as [BSD-3-Clause](http://opensource.org/licenses/BSD-3-Clause). Contributions
are welcome in the form of issue reports and pull requests.

All pull requests should include unit tests when applicable, and should follow
our coding standards (more on these below); failure to do so may result in
rejection of the pull request. If you need help writing tests, please ask on the
developer mailing list and/or in IRC.

## RESOURCES

If you wish to contribute to Apigility modules, please be sure to
read/subscribe to the following resources:

 -  [Coding Standards](https://github.com/zendframework/zf2/wiki/Coding-Standards)
 -  [ZF Git Guide](https://github.com/zendframework/zf2/blob/master/README-GIT.md)
 -  [Apigility developer mailing list](http://bit.ly/apigility-dev)
 -  Apigility developer IRC channel: #apigility-dev on Freenode.net

If you are working on new features, refactoring an existing module, or proposing
a new module, please send an email to the developer mailing list.

## REPORTING POTENTIAL SECURITY ISSUES

If you have encountered a potential security vulnerability in any Apigility
module, please report it to us at [zf-security@zend.com](mailto:zf-security@zend.com).
We will work with you to verify the vulnerability and patch it.

When reporting issues, please provide the following information:

- Module(s) affected
- A description indicating how to reproduce the issue
- A summary of the security vulnerability and impact

We request that you contact us via the email address above and give the project
contributors a chance to resolve the vulnerability and issue a new release prior
to any public exposure; this helps protect Apigility users, and provides them
with a chance to upgrade and/or update in order to protect their applications.

For sensitive email communications, please use 
[our PGP key](http://framework.zend.com/zf-security-pgp-key.asc).

## RUNNING TESTS

First, use [Composer](https://getcomposer.org) to install all dependencies:

```console
$ composer install
```

To run tests:

```console
$ composer test
```

## CODING STANDARDS

While Apigility uses Zend Framework 2 coding standards, in practice, we check
standards against PSR-1/2. To check for CS issues:

```console
$ composer cs-check
```

This will report CS issues. You can also attempt to fix many reported errors
automatically:

```console
$ composer cs-fix
```

If you use `cs-fix` to fix issues, make certain you add and commit any files
changed!

## Conduct

Please see our [CONDUCT.md](CONDUCT.md) to understand expected behavior when interacting with others in the project.
