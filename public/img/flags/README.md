# The fourteen member state flags

Source: [flag-icons](https://github.com/lipis/flag-icons) 7.2.3, MIT licensed,
fetched from jsDelivr on 9 September 2026. The full licence text is in
`LICENSE.txt` beside this file. MIT requires the copyright notice be retained
and does not require visible attribution on the page.

**Not drawn here and not approximated.** A wrong flag on an
intergovernmental platform is a diplomatic problem rather than a design one,
so every one of these is an upstream file, unmodified, and each was checked
against its authoritative description before it was installed. What was
checked is in the `verified_against` column of `LICENCES.csv`.

Two were checked with particular care because both have a superseded version
still in wide circulation:

- **Lesotho** must be the 2006 flag — blue, white and green with the black
  mokorotlo — and not the 1987 blue, white, green and red one.
- **Malawi** must be the 2012 reversion — black, red and green with the red
  rising sun on the black band — and not the 2010 variant with a white full
  sun on red.

**Eswatini keeps the ISO code SZ.** The 2018 rename from Swaziland did not
change the code and did not change the flag.

## If a flag changes

Replace the file, update `LICENCES.csv` with the new `downloaded_on` and what
was verified, and run the test suite: `FlagsTest` fails if any of the
fourteen member states has no flag file.
