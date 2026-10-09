# Synthetic maintenance invoices

Reusable manual upload and AI extraction fixtures. Every identity and transaction
is fictional; these documents are visibly marked **SYNTHETIC TEST INVOICE** and are
not valid for payment. They all refer to a Ducati Monster, model year 2022.

| File | Scenario | Expected total | Mileage | Labor |
| --- | --- | --- | --- | --- |
| `01-oil-service-en.pdf` | English oil/filter service, full workshop details | CHF 259.44 | 28,000 km | 60 minutes |
| `02-brake-service-en.pdf` | Same workshop, missing contact details; tests preserving existing contacts | CHF 270.25 | 41,000 km | 45 minutes |
| `03-tyre-service-de.pdf` / `.png` | German tyre service, EUR and 19% VAT | EUR 309.40 | 49,000 km | 30 minutes |
| `04-service-discount-fr.pdf` / `.jpg` | French service with a negative discount position and missing mileage/duration | CHF 281.06 | Unknown | Unknown |

Upload one document at a time from **Garage → motorcycle → Invoices**, extract,
review, then confirm. For fixture 04, mileage must be supplied before confirming;
the extractor should leave mileage and labor blank. Fixtures 01 and 02 deliberately
share the same workshop name/address; confirming both should reuse that workshop
within the account and preserve its existing email/tax identifier.

The images are raster copies of the corresponding PDFs, useful for image-input
checks. PDF and image versions represent the same invoice: confirming both creates
two maintenance records because separate uploads are not deduplicated. Use a test
motorcycle/account when you want to avoid adding fictional maintenance to your bike.

`expected.json` contains the printed factual values, including exact decimal amounts
and nullable missing fields. `suggested_title` is a reference summary, not an exact
text assertion; titles and uncertainty notes can legitimately vary. A correct
extractor must not invent the missing mileage, labor duration or workshop contacts.
There are no parts-compatibility or repair recommendations in these fixtures.

The `.html` files are retained as editable source. To regenerate a PDF locally:

```sh
google-chrome --headless --no-pdf-header-footer \
  --print-to-pdf="$PWD/fixtures/invoices/01-oil-service-en.pdf" \
  "file://$PWD/fixtures/invoices/01-oil-service-en.html"
```

To regenerate the raster copies (Poppler tools):

```sh
pdftoppm -r 150 -singlefile -png fixtures/invoices/03-tyre-service-de.pdf fixtures/invoices/03-tyre-service-de
pdftoppm -r 150 -singlefile -jpeg -jpegopt quality=90 fixtures/invoices/04-service-discount-fr.pdf fixtures/invoices/04-service-discount-fr
```
