.. _ref/api-problem-listener:

The API-Problem listener
========================

In the chapter on :ref:`error reporting <zfhal.error-reporting>`, I noted that
ZFHal has standardized on the API-Problem format for reporting errors.

Currently, an API-Problem response will be created automatically for any of the
following conditions:

- raising a ``ZF\Hal\Exception\CreationException`` inside a ``create``
  listener.
- raising a ``ZF\Hal\Exception\PatchException`` inside a ``patch``
  listener.
- raising a ``ZF\Hal\Exception\UpdateException`` inside an ``update``
  listener.
- raising an exception in any other ``ZF\Hal\Resource`` event listener.

If the exception you raise implements
``ZF\Hal\Exception\ProblemExceptionInterface`` -- which
``ZF\Hal\Exception\DomainException`` does, as does its descendents, the
``CreationException``, ``PatchException``, and ``UpdateException`` -- you can
set additional details in the exception instance before throwing it, allowing
you to hit to ``ZF\Hal\ApiProblem`` how to render itself.

There's another way to create and return an API-Problem payload, however: create
and return an instance of ``ZF\Hal\ApiProblem`` from any of the
``Resource`` event listeners. This gives you fine-grained control over creation
of the problem object, and avoids the overhead of exceptions.

However, there's another way to receive an API-Problem result: raising an
exception. For this the listener becomes important.

The Listener
------------

``ZF\Hal\Module`` registers a listener with the identifier
``ZF\Hal\ResourceController`` on its ``dispatch`` event. This event then
registers the ``ZF\Hal\ApiProblemListener`` on the application ``render``
event. Essentially, this ensures that the listener is only registered if a
controller intended as a RESTful resource endpoint is triggered.

The listener checks to see if the ``MvcEvent`` instance is marked as containing
an error. If so, it checks to see if the ``Accept`` header is looking for a JSON
response, and, finally, if so, it marshals an ``ApiProblem`` instance from the
exception, setting it as the result.

This latter bit, the ``Accept`` header matching, is configurable. If you want to
allow an API-Problem response for other than the default set of mediatypes
(``application/hal+json``, ``application/problem+json``, and
``application/json``), you can do so via your configuration. Set the value in
the ``accept_filter`` subkey of the ``zf-hal`` configuration; the value
should be a comma-separated set of mimetypes.

.. code-block:: php
    :linenos:

    return array(
        'zf-hal' => array(
            // ...
            'accept_filter' => 'application/json,text/json',
        ),
    );

.. index:: api-problem, error reporting, exception, accept
