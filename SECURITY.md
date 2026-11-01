# Security policy

## Reporting a vulnerability

Report security issues privately through GitHub Security Advisories:
**https://github.com/javierbeaumont/izartu/security/advisories/new**

Please include:
- A description of the issue
- Steps to reproduce
- The commit you are running (`git rev-parse HEAD`)
- Your PHP and MySQL versions
- Your suggested fix, if any

I'll acknowledge receipt within 5 business days and aim to have a fix or mitigation released within 30 days for
confirmed issues.

Do **not** open a public GitHub issue for security-sensitive reports.

## Supported versions

Only the latest release receives security fixes; while there is none, that means `main`.

izartu is self-hosted, so keeping your PHP, MySQL and web server patched is part of running it safely. The supported
versions of each are listed in the [README](README.md#requirements).

## Verifying the image

The container image on Docker Hub and GHCR is built by the [Container images](.github/workflows/container-images.yml)
workflow, which signs with Sigstore the provenance of each tag and the SBOM of each platform's image. With the
[GitHub CLI](https://cli.github.com), this checks that an image was built by that workflow from this repository:

```sh
gh attestation verify oci://docker.io/javierbeaumont/izartu:<tag> --repo javierbeaumont/izartu
```

On GHCR, the image is `ghcr.io/javierbeaumont/izartu`. A platform's SBOM is checked the same way, on its image's digest
(`docker buildx imagetools inspect` lists them) and with `--predicate-type https://spdx.dev/Document/v2.3`. A release's
source archive and `compose.yaml` are signed too, and checked on the downloaded file:

```sh
gh attestation verify izartu-<version>.tar.gz --repo javierbeaumont/izartu
```
