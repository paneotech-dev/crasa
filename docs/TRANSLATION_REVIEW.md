# Translations to review

Written by machine on 14 October 2026 at the platform owner's request. None has been checked by a French or Portuguese speaker yet.

## How to review

- **Database texts** (news, publications, instruments, projects, committee mandates, one page block): every row below has `is_machine_draft = 1`. In the back office, open **Translations**; each one shows as "Machine draft, not approved". Saving a field there (unchanged or rewritten) approves it and clears the flag.
- **Names** (countries, sectors, committees, indicators): these are in `lang/fr/names.php` and `lang/pt/names.php`. Correct them there.
- **News summaries**: 29 news items had no summary in any language. The English summaries are machine-written as well (marked the same way), so check the English first.

## Points the translators flagged

- NewsItem 48 (body, title): source typo "Thcretary General of PAPU" read as Secretary General; PAPU rendered UPAP (Union panafricaine des postes / União Pan-Africana dos Correios).
- NewsItem 49 body: English "falls on the October 2022" lacks the day; kept "en octobre 2022" / "em outubro de 2022".
- NewsItem 45 (title, body): DFS = SFN (fr) / SFD (pt); "ITU DFS SecurityLab" kept as "DFS Security Lab" in brackets.
- NewsItem 47 body: EACO kept with descriptive names; EAC = CAE (fr), EAC kept (pt).
- NewsItem 46 body: French opening drops the "(MoU)" acronym; Portuguese has "(MdE)".
- Committee 6 mandate: English says "fourteen authorities"; other pages list 13 members. Translated as written. Source needs checking.
- NewsItem 72 (title, body): PUASP rendered UPAEP; English ordinal <sup> tags left empty in FR/PT.
- NewsItem 67: the English article expands PUASP as "Pan African Postal Union", which is wrong (PUASP = Postal Union of the Americas, Spain and Portugal). Source needs correcting.
- NewsItem 74 body: English says "can be downloaded here" but there is no link.
- NewsItem 75 body: one paragraph appears twice in the English; kept twice.
- NewsItem 76 body: "Victoria Fall" corrected to Victoria Falls; SDGs = ODD (fr) / ODS (pt).
- NewsItem 52 summary: article body is empty; summary is generic, from the title only.
- NewsItem 57 summary: body only says "click on the download button"; summary says an EOI was issued.
- Project 6 summary: quotes styled per language.
- NewsItem 82 title: "small posts" read as small postal administrations.
- Publications 2, 3, 11, 12, 56, 69: "roaming" = itinérance (fr), roaming kept (pt).
- Publication 54 title: stray full stop dropped; PMR spelled out. Publication 65: PPDR spelled out.
- Project 7 body: "ingoing" read as "ongoing". Project 5 body: DPOs/NRAs = OPD/ANR (fr), OPD/ARN (pt), unofficial renderings.
- PageBlock 26 body: CRASA's name rendered descriptively; no official FR/PT name checked.
- NewsItem 79 body: English "Southern African Development Committee (SADC)" is a source error; translated with SADC's official name.
- NewsItem 80 body: English "fit inappropriately into the SADC project" read as "appropriately".
- NewsItem 81 body: English "schools and colleagues" probably means "colleges"; translated literally.
- NewsItem 77/80: DFS = SFN/SFD, USF = FSU, UASC kept, MoU = protocole d'accord / MdE.
- PAPU: UPAP in both languages (normalised across batches).
- NewsItem 51 (title, body): BEREC = ORECE (BEREC); unmatched closing quote in the English kept.
- NewsItem 51, 53, 56, 58, 59, 60 bodies: English ordinal <sup> tags left empty in FR/PT (French 1<sup>er</sup> kept).
- NewsItem 59 body: stray "]" in the English kept; contact's job title given in gender-neutral form (Responsable / Responsável).
- NewsItem 56 body: AGM = AGA.
- NewsItem 57 title: EOI = AMI (fr) / MDI (pt). Change back if CRASA keeps "EOI".
- NewsItem 67 (title, body): translated as written ("Union panafricaine des postes" / "União Postal Pan-Africana", PUASP) although the article is about UPAEP; fix the English and both translations together. "Secretary General of the UPU" kept literally (formally Director General).
- NewsItem 63, 64, 66, 68 bodies: ordinal <sup> tags dropped in FR/PT.
- NewsItem 62 body: stray "]" kept; neutral job titles.
- NewsItem 69 body: hashtags left in English; World Post Day theme wording not checked against UPU texts.
- NewsItem 64 title: "Electronic Committee" rendered as the committee's full name.
- Portuguese uses the post-1990 spelling (Diretor, setor, eletrónico); Angola has not adopted it. Decide which spelling CRASA wants.

## Names (lang files)

- Countries: Angola, Botswana, Comoros, DR Congo, Eswatini, Lesotho, Malawi, Mauritius, Mozambique, Namibia, South Africa, Tanzania, Zambia, Zimbabwe
- Sectors: Telecommunications, Broadcasting, Postal
- Committees: Spectrum and Frequency Management, Postal and Logistics, Legal and Regulatory Affairs, Economics, Consumer Affairs and Protection, Human and Institutional Capacity
- Indicators: 14 names and definitions

## Database texts (283 fields)

| Record | Field | Languages written | English title |
|---|---|---|---|
| Committee #1 | mandate | fr, pt |  |
| Committee #2 | mandate | fr, pt |  |
| Committee #3 | mandate | fr, pt |  |
| Committee #4 | mandate | fr, pt |  |
| Committee #5 | mandate | fr, pt |  |
| Committee #6 | mandate | fr, pt |  |
| Instrument #1 | summary | fr, pt |  |
| Instrument #2 | summary | fr, pt |  |
| Instrument #3 | summary | fr, pt |  |
| Instrument #4 | summary | fr, pt | Quality of service monitoring guidelines |
| Instrument #4 | title | pt | Quality of service monitoring guidelines |
| Instrument #5 | summary | fr, pt |  |
| Instrument #6 | summary | fr, pt | SIM registration directive |
| Instrument #6 | title | fr, pt | SIM registration directive |
| Instrument #7 | summary | fr, pt | Interconnection and infrastructure sharing regulations |
| Instrument #7 | title | fr, pt | Interconnection and infrastructure sharing regulations |
| Instrument #8 | summary | fr, pt | International roaming tariff guidelines |
| Instrument #8 | title | fr | International roaming tariff guidelines |
| Instrument #9 | summary | fr, pt | Electronic communications act |
| Instrument #9 | title | fr, pt | Electronic communications act |
| Instrument #10 | summary | fr, pt | IMT 2020 deployment roadmap |
| Instrument #10 | title | fr, pt | IMT 2020 deployment roadmap |
| Instrument #11 | summary | fr, pt | Broadcasting licence categories and conditions |
| Instrument #11 | title | fr, pt | Broadcasting licence categories and conditions |
| Instrument #12 | summary | fr, pt | Local content quota regulations |
| Instrument #12 | title | fr | Local content quota regulations |
| Instrument #13 | summary | fr, pt | Digital terrestrial television migration guidelines |
| Instrument #13 | title | pt | Digital terrestrial television migration guidelines |
| Instrument #14 | summary | fr, pt | Broadcasting infrastructure sharing directive |
| Instrument #14 | title | fr, pt | Broadcasting infrastructure sharing directive |
| Instrument #15 | summary | fr, pt | Community broadcasting policy |
| Instrument #15 | title | fr, pt | Community broadcasting policy |
| Instrument #16 | summary | fr, pt | Advertising standards for broadcast media |
| Instrument #16 | title | fr, pt | Advertising standards for broadcast media |
| Instrument #17 | summary | fr, pt | Broadcasting services act |
| Instrument #17 | title | fr, pt | Broadcasting services act |
| Instrument #18 | summary | fr, pt |  |
| Instrument #19 | summary | fr, pt | Postal quality of service standards |
| Instrument #19 | title | fr | Postal quality of service standards |
| Instrument #20 | summary | fr, pt | Courier and express operator licensing framework |
| Instrument #20 | title | fr, pt | Courier and express operator licensing framework |
| Instrument #21 | summary | fr, pt |  |
| Instrument #22 | summary | fr, pt | Address infrastructure and postcode directive |
| Instrument #22 | title | fr, pt | Address infrastructure and postcode directive |
| Instrument #23 | summary | fr, pt | Cross-border e-commerce parcel guidelines |
| Instrument #23 | title | fr, pt | Cross-border e-commerce parcel guidelines |
| Instrument #24 | summary | fr, pt | Postal services act |
| Instrument #24 | title | fr, pt | Postal services act |
| Instrument #25 | summary | fr, pt | Consumer complaint handling guidelines |
| Instrument #25 | title | fr, pt | Consumer complaint handling guidelines |
| Instrument #26 | summary | fr, pt | Data protection and lawful interception regulations |
| Instrument #26 | title | fr, pt | Data protection and lawful interception regulations |
| Instrument #27 | summary | fr, pt | Accessibility requirements for broadcast services |
| Instrument #27 | title | fr, pt | Accessibility requirements for broadcast services |
| Instrument #28 | summary | fr, pt | Permanent postal outlet coverage regulations |
| Instrument #28 | title | fr, pt | Permanent postal outlet coverage regulations |
| Instrument #29 | summary | fr, pt | Universal access and service fund policy |
| Instrument #29 | title | fr, pt | Universal access and service fund policy |
| Instrument #30 | summary | fr, pt | Spectrum release for digital dividend |
| Instrument #30 | title | fr, pt | Spectrum release for digital dividend |
| Instrument #31 | summary | fr, pt | Interconnection Guidelines, 2009 |
| Instrument #31 | title | fr, pt | Interconnection Guidelines, 2009 |
| Instrument #32 | summary | fr, pt | Interconnection Guidelines, 2016 revision |
| Instrument #32 | title | fr, pt | Interconnection Guidelines, 2016 revision |
| Instrument #33 | summary | fr, pt | Interconnection and Facilities Leasing Guidelines, 2023 |
| Instrument #33 | title | fr, pt | Interconnection and Facilities Leasing Guidelines, 2023 |
| NewsItem #45 | body | fr, pt | CRASA and ITU conducts a Workshop on Digital Financial Systems (DFS) Security |
| NewsItem #45 | summary | en, fr, pt | CRASA and ITU conducts a Workshop on Digital Financial Systems (DFS) Security |
| NewsItem #45 | title | fr, pt | CRASA and ITU conducts a Workshop on Digital Financial Systems (DFS) Security |
| NewsItem #46 | body | fr, pt | CRASA and Huawei sign MoU to Accelerate Digital Transformation in Africa |
| NewsItem #46 | summary | en, fr, pt | CRASA and Huawei sign MoU to Accelerate Digital Transformation in Africa |
| NewsItem #46 | title | fr, pt | CRASA and Huawei sign MoU to Accelerate Digital Transformation in Africa |
| NewsItem #47 | body | fr, pt | CRASA courtesy call on EACO |
| NewsItem #47 | summary | en, fr, pt | CRASA courtesy call on EACO |
| NewsItem #47 | title | fr, pt | CRASA courtesy call on EACO |
| NewsItem #48 | body | fr, pt | CRASA and PAPU sign memorandum of understanding |
| NewsItem #48 | summary | en, fr, pt | CRASA and PAPU sign memorandum of understanding |
| NewsItem #48 | title | fr, pt | CRASA and PAPU sign memorandum of understanding |
| NewsItem #49 | body | fr, pt | CRASA celebrates World Post Day |
| NewsItem #49 | summary | en, fr, pt | CRASA celebrates World Post Day |
| NewsItem #49 | title | fr, pt | CRASA celebrates World Post Day |
| NewsItem #50 | body | fr, pt | CRASA hosts Westwood International School students undertaking cybersecurity related resea |
| NewsItem #50 | summary | en, fr, pt | CRASA hosts Westwood International School students undertaking cybersecurity related resea |
| NewsItem #50 | title | fr, pt | CRASA hosts Westwood International School students undertaking cybersecurity related resea |
| NewsItem #51 | body | fr, pt | CRASA courtesy call on BEREC |
| NewsItem #51 | summary | en, fr, pt | CRASA courtesy call on BEREC |
| NewsItem #51 | title | fr, pt | CRASA courtesy call on BEREC |
| NewsItem #52 | body | fr, pt | International Girls in ICT Day |
| NewsItem #52 | summary | en, fr, pt | International Girls in ICT Day |
| NewsItem #52 | title | fr, pt | International Girls in ICT Day |
| NewsItem #53 | body | fr, pt | CRASA Workshop on Digital Financial Services (DFS) Security from 15th to 16th June 2023 |
| NewsItem #53 | summary | en, fr, pt | CRASA Workshop on Digital Financial Services (DFS) Security from 15th to 16th June 2023 |
| NewsItem #53 | title | fr, pt | CRASA Workshop on Digital Financial Services (DFS) Security from 15th to 16th June 2023 |
| NewsItem #54 | body | fr, pt | CRASA courtesy call on ITU BDT director |
| NewsItem #54 | summary | en, fr, pt | CRASA courtesy call on ITU BDT director |
| NewsItem #54 | title | fr, pt | CRASA courtesy call on ITU BDT director |
| NewsItem #55 | body | fr, pt | CRASA and Huawei Partnered to Convene Emerging Issues in Spectrum Management Workshop, Gab |
| NewsItem #55 | summary | en, fr, pt | CRASA and Huawei Partnered to Convene Emerging Issues in Spectrum Management Workshop, Gab |
| NewsItem #55 | title | fr, pt | CRASA and Huawei Partnered to Convene Emerging Issues in Spectrum Management Workshop, Gab |
| NewsItem #56 | body | fr, pt | Call for Stakeholder Comments – Discussion Paper on Regulatory Aspects of Digital Transfor |
| NewsItem #56 | summary | en, fr, pt | Call for Stakeholder Comments – Discussion Paper on Regulatory Aspects of Digital Transfor |
| NewsItem #56 | title | fr, pt | Call for Stakeholder Comments – Discussion Paper on Regulatory Aspects of Digital Transfor |
| NewsItem #57 | body | fr, pt | Request for expression of interest (EOI) |
| NewsItem #57 | summary | en, fr, pt | Request for expression of interest (EOI) |
| NewsItem #57 | title | fr, pt | Request for expression of interest (EOI) |
| NewsItem #58 | body | fr, pt | CRASA Engaged by the UPU to Deliver Capacity-Building Workshop on Postal Sector Regulation |
| NewsItem #58 | summary | en, fr, pt | CRASA Engaged by the UPU to Deliver Capacity-Building Workshop on Postal Sector Regulation |
| NewsItem #58 | title | fr, pt | CRASA Engaged by the UPU to Deliver Capacity-Building Workshop on Postal Sector Regulation |
| NewsItem #59 | body | fr, pt | CRASA Appoints Dr Stanley Shanapinda of Namibia as Executive Secretary (CEO) |
| NewsItem #59 | summary | en, fr, pt | CRASA Appoints Dr Stanley Shanapinda of Namibia as Executive Secretary (CEO) |
| NewsItem #59 | title | fr, pt | CRASA Appoints Dr Stanley Shanapinda of Namibia as Executive Secretary (CEO) |
| NewsItem #60 | body | fr, pt | Call for applications for Cullen telecoms regulatory masterclass scholarships |
| NewsItem #60 | summary | en, fr, pt | Call for applications for Cullen telecoms regulatory masterclass scholarships |
| NewsItem #60 | title | fr, pt | Call for applications for Cullen telecoms regulatory masterclass scholarships |
| NewsItem #61 | body | fr, pt | CRASA 15th annual general meeting, Lilongwe, Malawi, 16th to 17th April 2026 |
| NewsItem #61 | summary | en, fr, pt | CRASA 15th annual general meeting, Lilongwe, Malawi, 16th to 17th April 2026 |
| NewsItem #61 | title | fr, pt | CRASA 15th annual general meeting, Lilongwe, Malawi, 16th to 17th April 2026 |
| NewsItem #62 | body | fr, pt | Malawi assumes chairpersonship of the communications regulators' association of Southern A |
| NewsItem #62 | summary | en, fr, pt | Malawi assumes chairpersonship of the communications regulators' association of Southern A |
| NewsItem #62 | title | fr, pt | Malawi assumes chairpersonship of the communications regulators' association of Southern A |
| NewsItem #63 | body | fr, pt | CRASA Chairperson pays courtesy visit to BOCRA |
| NewsItem #63 | summary | en, fr, pt | CRASA Chairperson pays courtesy visit to BOCRA |
| NewsItem #63 | title | fr, pt | CRASA Chairperson pays courtesy visit to BOCRA |
| NewsItem #64 | body | fr, pt | CRASA 2nd Electronic Committee Meeting 2023/24 |
| NewsItem #64 | summary | en, fr, pt | CRASA 2nd Electronic Committee Meeting 2023/24 |
| NewsItem #64 | title | fr, pt | CRASA 2nd Electronic Committee Meeting 2023/24 |
| NewsItem #65 | body | fr, pt | CRASA courtesy call on INCM Chairperson |
| NewsItem #65 | summary | en, fr, pt | CRASA courtesy call on INCM Chairperson |
| NewsItem #65 | title | fr, pt | CRASA courtesy call on INCM Chairperson |
| NewsItem #66 | body | fr, pt | Announcement of crasa-cullen scholarship opportunity to CRASA members |
| NewsItem #66 | summary | en, fr, pt | Announcement of crasa-cullen scholarship opportunity to CRASA members |
| NewsItem #66 | title | fr, pt | Announcement of crasa-cullen scholarship opportunity to CRASA members |
| NewsItem #67 | body | fr, pt | CRASA and PUASP Sign Memorandum of Understanding to Enhance Regional Cooperation in ICT an |
| NewsItem #67 | summary | en, fr, pt | CRASA and PUASP Sign Memorandum of Understanding to Enhance Regional Cooperation in ICT an |
| NewsItem #67 | title | fr, pt | CRASA and PUASP Sign Memorandum of Understanding to Enhance Regional Cooperation in ICT an |
| NewsItem #68 | body | fr, pt | CRASA Facilitates Capacity-Building Workshop on Postal Sector Regulation in the Kingdom of |
| NewsItem #68 | summary | en, fr, pt | CRASA Facilitates Capacity-Building Workshop on Postal Sector Regulation in the Kingdom of |
| NewsItem #68 | title | fr, pt | CRASA Facilitates Capacity-Building Workshop on Postal Sector Regulation in the Kingdom of |
| NewsItem #69 | body | fr, pt | World Post Day 9th October 2025 |
| NewsItem #69 | summary | en, fr, pt | World Post Day 9th October 2025 |
| NewsItem #69 | title | fr, pt | World Post Day 9th October 2025 |
| NewsItem #70 | body | fr, pt | CRASA Hosts Workshop on Artificial Intelligence(AI) for Human Resource(HR) Professionals i |
| NewsItem #70 | summary | en, fr, pt | CRASA Hosts Workshop on Artificial Intelligence(AI) for Human Resource(HR) Professionals i |
| NewsItem #70 | title | fr, pt | CRASA Hosts Workshop on Artificial Intelligence(AI) for Human Resource(HR) Professionals i |
| NewsItem #71 | body | fr, pt | CRASA Postal Committee Advances Regional Resilience and Cooperation |
| NewsItem #71 | summary | en, fr, pt | CRASA Postal Committee Advances Regional Resilience and Cooperation |
| NewsItem #71 | title | fr, pt | CRASA Postal Committee Advances Regional Resilience and Cooperation |
| NewsItem #72 | body | fr, pt | CRASA Participates at the High Level Postal Forum for Central America and other Strategic  |
| NewsItem #72 | summary | en, fr, pt | CRASA Participates at the High Level Postal Forum for Central America and other Strategic  |
| NewsItem #72 | title | fr, pt | CRASA Participates at the High Level Postal Forum for Central America and other Strategic  |
| NewsItem #73 | body | fr, pt | CRASA appoints a new Executive Secretary |
| NewsItem #73 | summary | fr, pt | CRASA appoints a new Executive Secretary |
| NewsItem #73 | title | fr, pt | CRASA appoints a new Executive Secretary |
| NewsItem #74 | body | fr, pt | SADC radio frequency spectrum allocation plan 2020 |
| NewsItem #74 | summary | fr, pt | SADC radio frequency spectrum allocation plan 2020 |
| NewsItem #74 | title | fr, pt | SADC radio frequency spectrum allocation plan 2020 |
| NewsItem #75 | body | fr, pt | The impact of COVID-19 on human resources management |
| NewsItem #75 | summary | fr, pt | The impact of COVID-19 on human resources management |
| NewsItem #75 | title | fr, pt | The impact of COVID-19 on human resources management |
| NewsItem #76 | body | fr, pt | Crasa's virtual 10th annual general meeting |
| NewsItem #76 | summary | fr, pt | Crasa's virtual 10th annual general meeting |
| NewsItem #76 | title | fr, pt | Crasa's virtual 10th annual general meeting |
| NewsItem #77 | body | fr, pt | Enhancing collaborative regulation for digital economies |
| NewsItem #77 | summary | fr, pt | Enhancing collaborative regulation for digital economies |
| NewsItem #77 | title | fr, pt | Enhancing collaborative regulation for digital economies |
| NewsItem #78 | body | fr, pt | Supporting regional interests at the 10th PAPU plenipotentiary conference |
| NewsItem #78 | summary | fr, pt | Supporting regional interests at the 10th PAPU plenipotentiary conference |
| NewsItem #78 | title | fr, pt | Supporting regional interests at the 10th PAPU plenipotentiary conference |
| NewsItem #79 | body | fr, pt | CRASA 13th Annual General Meeting |
| NewsItem #79 | summary | en, fr, pt | CRASA 13th Annual General Meeting |
| NewsItem #79 | title | fr, pt | CRASA 13th Annual General Meeting |
| NewsItem #80 | body | fr, pt | Masterclass on the broadband gap analysis and mapping |
| NewsItem #80 | summary | fr, pt | Masterclass on the broadband gap analysis and mapping |
| NewsItem #80 | title | fr, pt | Masterclass on the broadband gap analysis and mapping |
| NewsItem #81 | body | fr, pt | Gender equality within the postal industry |
| NewsItem #81 | summary | fr, pt | Gender equality within the postal industry |
| NewsItem #81 | title | fr, pt | Gender equality within the postal industry |
| NewsItem #82 | body | fr, pt | Strategies for small posts |
| NewsItem #82 | summary | fr, pt | Strategies for small posts |
| NewsItem #82 | title | fr, pt | Strategies for small posts |
| PageBlock #26 | body | fr, pt |  |
| Project #5 | body | fr, pt | CRASA-UPU Regional Postal Quality of Service Project |
| Project #5 | summary | fr, pt | CRASA-UPU Regional Postal Quality of Service Project |
| Project #5 | title | fr, pt | CRASA-UPU Regional Postal Quality of Service Project |
| Project #6 | body | fr, pt | IMT 2020 including 5G technologies deployment roadmap for SADC |
| Project #6 | summary | fr, pt | IMT 2020 including 5G technologies deployment roadmap for SADC |
| Project #6 | title | fr, pt | IMT 2020 including 5G technologies deployment roadmap for SADC |
| Project #7 | body | fr, pt | SADC Postal Financial Inclusion Study and Guidelines (2018) |
| Project #7 | summary | fr, pt | SADC Postal Financial Inclusion Study and Guidelines (2018) |
| Project #7 | title | fr, pt | SADC Postal Financial Inclusion Study and Guidelines (2018) |
| Project #8 | body | fr, pt | SADC postal sector reform |
| Project #8 | summary | fr, pt | SADC postal sector reform |
| Project #8 | title | fr, pt | SADC postal sector reform |
| Publication #2 | summary | pt | SADC roaming guidelines, second edition |
| Publication #2 | title | pt | SADC roaming guidelines, second edition |
| Publication #3 | summary | fr, pt | SADC roaming guidelines, third edition |
| Publication #3 | title | fr, pt | SADC roaming guidelines, third edition |
| Publication #4 | summary | pt | SADC radio frequency spectrum policy model framework |
| Publication #4 | title | pt | SADC radio frequency spectrum policy model framework |
| Publication #5 | summary | fr | Postal service and access guidelines |
| Publication #5 | title | fr | Postal service and access guidelines |
| Publication #6 | summary | fr, pt | Broadcasting infrastructure sharing guidelines |
| Publication #6 | title | fr, pt | Broadcasting infrastructure sharing guidelines |
| Publication #8 | summary | fr, pt | Consumer protection in converged markets |
| Publication #8 | title | fr, pt | Consumer protection in converged markets |
| Publication #9 | summary | fr, pt | Universal access and service funds in the region |
| Publication #9 | title | fr, pt | Universal access and service funds in the region |
| Publication #10 | summary | pt | Communique of the Annual General Meeting |
| Publication #10 | title | pt | Communique of the Annual General Meeting |
| Publication #11 | summary | pt | SADC Roaming Guidelines, 2015 |
| Publication #11 | title | pt | SADC Roaming Guidelines, 2015 |
| Publication #12 | summary | pt | SADC Roaming Guidelines, 2024 revision |
| Publication #12 | title | pt | SADC Roaming Guidelines, 2024 revision |
| Publication #48 | summary | fr, pt | Guidelines on Interconnection for SADC Countries |
| Publication #48 | title | fr, pt | Guidelines on Interconnection for SADC Countries |
| Publication #49 | summary | fr, pt | Guidelines on Tariff for SADC Countries |
| Publication #49 | title | fr, pt | Guidelines on Tariff for SADC Countries |
| Publication #50 | summary | fr, pt | Policy Guidelines in Licensing of Telecommunications in SADC |
| Publication #50 | title | fr, pt | Policy Guidelines in Licensing of Telecommunications in SADC |
| Publication #51 | summary | fr, pt | Wholesale Pricing Guidelines for ICT Sector for SADC Edition 2002 |
| Publication #51 | title | fr, pt | Wholesale Pricing Guidelines for ICT Sector for SADC Edition 2002 |
| Publication #52 | summary | fr, pt | Guidelines on Numbering Harmonisation for SADC Countries Edition 2003 |
| Publication #52 | title | fr, pt | Guidelines on Numbering Harmonisation for SADC Countries Edition 2003 |
| Publication #53 | summary | fr, pt | Toolkit on Universal Access funding and USF Implementation |
| Publication #53 | title | fr, pt | Toolkit on Universal Access funding and USF Implementation |
| Publication #54 | summary | fr, pt | Guidelines on the Coordination of Frequencies in. the VHF/UHF 2-Way Radiocommunications in |
| Publication #54 | title | fr, pt | Guidelines on the Coordination of Frequencies in. the VHF/UHF 2-Way Radiocommunications in |
| Publication #55 | summary | fr, pt | How to Develop National Broadband Plan Guidelines Edition 2015 |
| Publication #55 | title | fr, pt | How to Develop National Broadband Plan Guidelines Edition 2015 |
| Publication #56 | summary | fr, pt | SADC Roaming Policy Edition 2015 |
| Publication #56 | title | fr, pt | SADC Roaming Policy Edition 2015 |
| Publication #57 | summary | fr, pt | SADC ICT and Broadcasting Infrastructure Sharing Guidelines |
| Publication #57 | title | fr, pt | SADC ICT and Broadcasting Infrastructure Sharing Guidelines |
| Publication #58 | summary | fr, pt | Framework on Establishment of Digital Sound Broadcasting in SADC |
| Publication #58 | title | fr, pt | Framework on Establishment of Digital Sound Broadcasting in SADC |
| Publication #59 | summary | fr, pt | The Rural Broadband Guidelines and Recommendations |
| Publication #59 | title | fr, pt | The Rural Broadband Guidelines and Recommendations |
| Publication #60 | summary | fr, pt | The Digital Financial Inclusion: Key Regulatory Principles and Guidelines for Mobile Finan |
| Publication #60 | title | fr, pt | The Digital Financial Inclusion: Key Regulatory Principles and Guidelines for Mobile Finan |
| Publication #61 | summary | fr, pt | Guidelines on ICT Quality of Service and Quality of Experience for SADC |
| Publication #61 | title | fr, pt | Guidelines on ICT Quality of Service and Quality of Experience for SADC |
| Publication #62 | summary | fr, pt | Competition Assessment Framework for ICT Sector in SADC |
| Publication #62 | title | fr, pt | Competition Assessment Framework for ICT Sector in SADC |
| Publication #63 | summary | fr, pt | How to Conduct Broadband Gap Analysis in SADC |
| Publication #63 | title | fr, pt | How to Conduct Broadband Gap Analysis in SADC |
| Publication #64 | summary | fr, pt | SADC Framework for the Harmonisation of Frequency Radio Spectrum for Short-Range Devices ( |
| Publication #64 | title | fr, pt | SADC Framework for the Harmonisation of Frequency Radio Spectrum for Short-Range Devices ( |
| Publication #65 | summary | fr, pt | SADC Framework for Harmonisation of Allocation of RF Spectrum for PPDR |
| Publication #65 | title | fr, pt | SADC Framework for Harmonisation of Allocation of RF Spectrum for PPDR |
| Publication #66 | summary | fr, pt | SADC Radio Frequency Spectrum Allocation Plan (RFSAP) |
| Publication #66 | title | fr, pt | SADC Radio Frequency Spectrum Allocation Plan (RFSAP) |
| Publication #67 | summary | fr, pt | SADC Footnotes Relating to Radio Frequency Spectrum Allocation Plan (RFSAP) |
| Publication #67 | title | fr, pt | SADC Footnotes Relating to Radio Frequency Spectrum Allocation Plan (RFSAP) |
| Publication #68 | summary | fr, pt | SADC Radio Frequency Spectrum Policy Model Framework |
| Publication #68 | title | fr, pt | SADC Radio Frequency Spectrum Policy Model Framework |
| Publication #69 | summary | fr, pt | SADC Roaming Guidelines |
| Publication #69 | title | fr, pt | SADC Roaming Guidelines |
| Publication #70 | summary | fr, pt | Consumer Rights and Protection Guidelines for the Electronic Communications Sector in SADC |
| Publication #70 | title | fr, pt | Consumer Rights and Protection Guidelines for the Electronic Communications Sector in SADC |
| Publication #71 | summary | fr, pt | Universal Access and Service for Electronic Communications Sector in SADC Guidelines |
| Publication #71 | title | fr, pt | Universal Access and Service for Electronic Communications Sector in SADC Guidelines |
| Publication #72 | summary | fr, pt | Postal Quality of Service (QoS) Monitoring Guidelines |
| Publication #72 | title | fr, pt | Postal Quality of Service (QoS) Monitoring Guidelines |
| Publication #73 | summary | fr, pt | SADC Postal Costs and Tariff Regulation Guidelines |
| Publication #73 | title | fr, pt | SADC Postal Costs and Tariff Regulation Guidelines |
| Publication #74 | summary | fr, pt | Postal Ecommerce and Trade Facilitation Guidelines |
| Publication #74 | title | fr, pt | Postal Ecommerce and Trade Facilitation Guidelines |
| Publication #75 | summary | fr, pt | SADC Postal Financial Inclusion Guidelines |
| Publication #75 | title | fr, pt | SADC Postal Financial Inclusion Guidelines |
| Publication #76 | summary | fr, pt | SADC Postal Market Definition and Licensing Guidelines |
| Publication #76 | title | fr, pt | SADC Postal Market Definition and Licensing Guidelines |
| Publication #77 | summary | fr, pt | SADC Interconnection and Postal Network Access Guidelines |
| Publication #77 | title | fr, pt | SADC Interconnection and Postal Network Access Guidelines |
| Publication #78 | summary | fr, pt | SADC Universal Postal Service and Access Guidelines |
| Publication #78 | title | fr, pt | SADC Universal Postal Service and Access Guidelines |
| Publication #79 | summary | fr, pt | SADC Postal Market Definition and Licensing Guidelines |
| Publication #79 | title | fr, pt | SADC Postal Market Definition and Licensing Guidelines |
| Publication #80 | summary | fr, pt | Universal Postal Service & Access Guidelines |
| Publication #80 | title | fr, pt | Universal Postal Service & Access Guidelines |
| Publication #81 | summary | fr, pt | Postal E-commerce and Trade Facilitation Guidelines |
| Publication #81 | title | fr, pt | Postal E-commerce and Trade Facilitation Guidelines |
| Publication #82 | summary | fr, pt | CRASA annual reports |
| Publication #82 | title | fr, pt | CRASA annual reports |
