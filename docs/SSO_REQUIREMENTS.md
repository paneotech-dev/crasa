# Single sign-on: what CRASA needs to supply

**This document is written to be forwarded as it stands.** It asks for
exactly what is needed to build the bridge and nothing else, and it says
what happens if the answers do not arrive.

---

## Where this stands today

The platform authenticates against its own user store. Accounts are created
by invitation from the CRASA Secretariat, passwords are set by the account
holder from a link, two-factor authentication is mandatory for
administrators, and every sign-in and failed attempt is recorded.

**That is a complete and deliverable answer to IN-01.** If the answers below
never arrive, the platform ships as it is and works. Nothing is blocked.

The single sign-on bridge is built behind an interface, so adding it later
changes one binding and no application code. What follows is what that
implementation needs.

---

## What we need, in one list

### 1. Which platform is the identity source

Name the system that holds the account somebody signs in with.

- [ ] The CRASA Extranet
- [ ] The Event Management Platform
- [ ] A third system
- [ ] Microsoft Entra ID, Google Workspace or similar

If more than one, say which is authoritative when they disagree about the
same person.

### 2. Which protocol it speaks

- [ ] SAML 2.0
- [ ] OpenID Connect / OAuth 2.0
- [ ] Neither, or unknown

Anything else needs a conversation before it is quoted.

### 3. The endpoints

**For SAML 2.0**, either the IdP metadata URL, or all of:

- Entity ID
- Single sign-on URL, and whether it accepts HTTP-Redirect, HTTP-POST or both
- Single logout URL, if there is one
- The signing certificate, as a `.pem` or `.cer` file
- Whether assertions are signed, encrypted, or both

**For OpenID Connect**, either the discovery document URL
(`/.well-known/openid-configuration`), or all of:

- Issuer
- Authorization endpoint
- Token endpoint
- JWKS URI
- Client ID and client secret for this platform
- The scopes we are permitted to request

### 4. What the assertion carries about a person

We need to know which attribute is the stable identifier, and it must not be
the email address: people change addresses and an email as a primary key
silently merges or orphans an account when they do.

| We need | Attribute name in your system |
|---|---|
| A stable identifier that never changes | |
| Email address | |
| Full name | |
| Member state or authority, if carried | |
| Group or role, if carried | |

If the member state is not carried in the assertion, say so. It is not a
blocker: the Secretariat sets it on the account here, which is where it is
set today.

### 5. Whether the identity provider may create accounts here

**Our recommendation is no, and this is the one answer with a security
consequence.**

If federated identity may create accounts, then anyone your identity
provider trusts becomes a user of the regulatory platform without anyone at
the Secretariat deciding so. The platform holds country-scoped data and
confidential committee papers.

- [ ] No. An assertion for someone with no account here is refused, and the
      Secretariat invites people as it does now. **Recommended.**
- [ ] Yes, and every account so created starts as a Viewer with no member
      state until an administrator assigns one.

### 6. Two test accounts

We cannot build a bridge we cannot exercise.

- One ordinary member state account
- One Secretariat account

Test credentials in a non-production environment are fine and preferred.

### 7. Who to ask when it does not work

A named technical contact at the identity provider, and the hours they
answer in. A bridge that fails at nine in the morning in Gaborone is not a
support ticket anyone should be raising through a procurement channel.

---

## What we will do with the answers

1. Implement `App\Auth\IdentityProvider` against the named protocol.
2. Bind it in place of `PlatformIdentityProvider`. One line.
3. Add the platform's own metadata or redirect URI to your allow list. We
   will send you those values; they are:
   - Assertion Consumer Service / redirect URI: `https://<host>/{locale}/sso/callback`
   - Entity ID / client identifier: to be agreed, `crasa-platform` proposed
4. Test both accounts through the four failure paths already built: not
   configured, no assertion, no account here, and account suspended.
5. Keep the password form. **Single sign-on does not replace it.** A bridge
   that is the only way in becomes an outage for the whole platform when the
   identity provider has one, and a regional regulator locked out of its own
   data submission window has a real problem.

---

## Two-factor, whichever way this goes

CLAUDE.md and the contract make two-factor mandatory for administrators.
Federated sign-in is a claim about who someone is; it is not a second
factor. An administrator arriving through the bridge still enrols and still
challenges, and that is deliberate rather than an oversight.

If your identity provider already enforces multi-factor authentication and
asserts that it did, tell us the attribute that carries it and we will
honour it instead of challenging twice.

---

## If nothing comes back

The platform ships with its own authentication, which is built, tested and
audited. The bridge is added whenever the answers arrive, without a
migration and without downtime, because no account depends on it.

The only thing that gets worse with delay is that people build habits around
one set of credentials and then have to change them.
