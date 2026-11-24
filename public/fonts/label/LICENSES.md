# Label fonts

Static TrueType files (regular 400 and bold 700) from Google Fonts, used by the label designer,
the browser print page and the PDF renderer (`app/Domain/Printing/LabelFonts.php`).
All of them may be embedded in documents and redistributed.

| Font | License |
|---|---|
| Roboto, Roboto Condensed | Apache License 2.0 |
| Arimo, Tinos | Apache License 2.0 |
| Open Sans, Montserrat, Noto Sans, Oswald | SIL Open Font License 1.1 |
| PT Sans, PT Serif | SIL Open Font License 1.1 |

License texts: https://www.apache.org/licenses/LICENSE-2.0 and https://openfontlicense.org

When a file is replaced, recompute its PDF line-height in `LabelFonts::FONTS` and check which
fonts lack the Uzbek ʻ (U+02BB).
