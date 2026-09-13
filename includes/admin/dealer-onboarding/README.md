# Browser-free dealership onboarding

This feature creates dealership WordPress accounts and matching Bazaraki sync
profiles through an administrator-only REST endpoint. It is intended for the
local PowerShell runner in `scripts/autoagora-onboard-dealers.ps1`.

## One-time setup

1. Deploy this theme version.
2. In WordPress, open **Tools > Dealer Onboarding API** and generate the
   dedicated token. Copy it immediately because WordPress shows it only once.
3. From the theme directory, run the command below and paste the token when
   prompted:

   ```powershell
   .\scripts\autoagora-onboard-dealers.ps1 -Configure
   ```

The dedicated token can access only the two dealership-onboarding REST routes;
it is not a general WordPress login. It is stored with Windows DPAPI and can be
decrypted only by the same Windows user on the same computer. It is not stored
in the repo. The token can be replaced or revoked from the same Tools page.

List existing dealership accounts and their sync profile IDs without opening
WordPress:

```powershell
.\scripts\autoagora-onboard-dealers.ps1 -ListExisting
```

## Run a batch

Copy `scripts/dealer-onboarding.sample.json`, fill in the researched dealers,
and validate it first:

```powershell
.\scripts\autoagora-onboard-dealers.ps1 -InputFile .\dealers.json
```

If validation passes, create the accounts and profiles:

```powershell
.\scripts\autoagora-onboard-dealers.ps1 -InputFile .\dealers.json -Commit
```

Generated passwords are never printed during creation. They are saved as a
Windows-user-encrypted CliXml file in Documents. Use the script's
`-RevealCredentialsFile` mode only when the passwords need to be viewed.

The runner uses the Windows-provided `curl.exe` transport because the production
hosting firewall blocks PowerShell's built-in HTTP client. The scoped token is
passed to curl through a temporary process environment variable rather than a
command-line argument, then removed immediately after each request.

## Phone-source rule

`phone_source_url` is mandatory and should point to the dealership's own site,
Google Maps, or another independent public source. A Bazaraki URL is rejected as
the phone source unless both of these are present:

- `allow_bazaraki_phone_fallback` is `true`.
- `phone_search_notes` contains a meaningful description of the other sources
  checked first.

The source URL, source label, fallback status, and search notes are stored on the
created user as audit metadata. AutoAgora's own dealer pages are never accepted
as phone evidence because their original source may be Bazaraki.

`bazaraki_url` accepts the English `/c/{dealer}/` and `/items/author/{id}/`
profile formats, not an individual vehicle advert.

Keep working batch files outside the repository, or name a local working copy
`dealer-onboarding.local.json` so Git ignores the personal contact data.

New sync profiles are always created disabled, excluded from worker runs, and in
dry-run mode. Enabling a profile remains a separate administrator decision.
