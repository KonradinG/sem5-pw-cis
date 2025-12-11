# Security Overview

This document complements `SECURITY_PIPELINE.md` and adds details about the Software Bill of Materials (SBOM) process.

## Quick Access

- 📊 [Live Security Dashboard](https://konrading.github.io/sem5-pw-cis/) – Current vulnerability status and trends
- 📦 [SBOM Download](https://github.com/KonradinG/sem5-pw-cis/actions/workflows/sbom.yml) – Software Bill of Materials artifacts
- 📝 [Audit Logs](https://github.com/KonradinG/sem5-pw-cis/tree/main/audit-logs) – Historical scan records per image
- 🔧 [Technical Pipeline Documentation](SECURITY_PIPELINE.md) – Detailed architecture and workflows

## SBOM / Software Bill of Materials

### Purpose

The SBOM provides an inventory of all components (packages, modules, dependencies) present in this repository at a given point in time. It increases transparency, enables faster vulnerability correlation, and supports compliance / customer security assessments.

### Format

- Generated using Syft
- Output format: CycloneDX JSON (`cyclonedx-json`)
- Stable file path within artifact: `sbom/sbom-cyclonedx.json` (prepared for future diffing)

### Generation Workflow

A dedicated GitHub Actions workflow (`sbom.yml`):

- Trigger: manual (`workflow_dispatch`) or scheduled monthly (1st day at 02:00 UTC)
- No code changes are committed; SBOM is uploaded as an artifact only
- Independent from vulnerability scanning (no coupling with Trivy pipeline)
- Includes test images from the [example-voting-app](https://github.com/dockersamples/example-voting-app) (added as test per Prof. Nestler's recommendation)

**Note on Test Images:** The example-voting-app images contain known vulnerabilities and are excluded from the CVE-Gate blocking mechanism to demonstrate full pipeline capabilities including high-severity vulnerability tracking and reporting.

### How to Obtain the SBOM

1. Navigate to: Actions → `SBOM Generation` workflow runs
2. Select the latest successful run
3. Download artifact: `sbom-cyclonedx`
4. Inside the artifact, open `sbom/sbom-cyclonedx.json` for local analysis

### Typical Use Cases

- Customer security reviews
- Internal dependency audits
- Rapid impact analysis when new CVEs are disclosed
- Input for external tooling (e.g. vulnerability correlation platforms)

### Validation & Integrity

- The SBOM is generated directly from the repository contents without modification
- For reproducibility, rerun the workflow at any time (manual dispatch)
- Retention: artifact kept for 90 days (subject to org/repo policy limits)

### Recommended Tools for Consumers (Optional)

| Tool  | Purpose                         |
| ----- | ------------------------------- |
| Syft  | Local SBOM generation / diffing |
| Grype | Vulnerability matching vs SBOM  |
| jq    | Filtering CycloneDX JSON fields |

### Quick Local Re-Generation (Optional)

To produce a local CycloneDX SBOM (requires Syft):

```bash
syft dir:. -o cyclonedx-json > sbom-local-cyclonedx.json
```

## Vulnerability Scanning (Reference)

For scanning details, see `SECURITY_PIPELINE.md` — Trivy provides vulnerability data used for weekly summaries and GitHub Security Code Scanning via SARIF upload.

## Contact & Reporting

For security-related questions or responsible disclosure, open a GitHub Issue with the label `security` or contact the maintainers.

---

Maintained as part of continuous security transparency efforts.
