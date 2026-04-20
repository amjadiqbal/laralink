# Security Policy

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 0.1.x   | ✅        |

## Reporting a Vulnerability

**Please do not open a public GitHub issue for security vulnerabilities.**

Send a description of the vulnerability to the maintainer by email or through
[GitHub private vulnerability reporting](https://docs.github.com/en/code-security/security-advisories/guidance-on-reporting-and-writing/privately-reporting-a-security-vulnerability)
for this repository.

Please include:

- A description of the vulnerability and its potential impact.
- Steps to reproduce the issue or a proof-of-concept.
- The version(s) of Laralink affected.
- Any suggested remediation if you have one.

You will receive a response within **72 hours**. If the issue is confirmed, a fix will be
prioritised and a new release published. You will be credited in the release notes unless you
prefer to remain anonymous.

## Scope

Laralink is a CLI developer tool that manipulates `composer.json` and runs `git`/`composer`
subprocesses. Security considerations include (but are not limited to):

- Unsafe handling of user-supplied paths or package names that could lead to unintended file
  system operations.
- Command injection through unsanitised shell arguments passed to subprocess invocations.
- Unvalidated writes to `composer.json` that could corrupt a project's dependency manifest.
