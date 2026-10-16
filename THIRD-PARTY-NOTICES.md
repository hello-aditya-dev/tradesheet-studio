# Third-Party Notices

TradeSheet Studio is distributed under **GPL-2.0-or-later** (see `LICENSE`).
This file gives attribution for the third-party components bundled in the
runtime distribution (`vendor/` inside the plugin). Full license texts ship
inside each package directory — this file carries the required notices and
pointers, not the full texts.

The complete audit (versions, sources, obligations, advisory status) is
`docs/legal/THIRD_PARTY_LICENSE_AUDIT.md`.

## dompdf/dompdf — v3.1.6

- Source: https://packagist.org/packages/dompdf/dompdf
- License: **LGPL-2.1** (`vendor/dompdf/dompdf/LICENSE.LGPL`)
- © the dompdf authors and contributors (see `AUTHORS.md` in the package).
- Distributed **unmodified**. LGPL §4 notice: this library is distributed
  as a separate work alongside GPL-licensed code; its source is available
  at the URL above; no modifications have been made.

## dompdf/php-font-lib — 1.0.2

- Source: https://packagist.org/packages/dompdf/php-font-lib
- License: **LGPL-2.1-or-later** (`vendor/dompdf/php-font-lib/LICENSE`)
- © Brent Bryan, dompdf contributors.
- Distributed **unmodified**, as a separate work; source available at the
  URL above.

## dompdf/php-svg-lib — 1.0.2

- Source: https://packagist.org/packages/dompdf/php-svg-lib
- License: **LGPL-3.0-or-later** (`vendor/dompdf/php-svg-lib/LICENSE`)
- © the dompdf community (see `AUTHORS.md` in the package).
- Distributed **unmodified**, as a separate work; source available at the
  URL above.

## masterminds/html5 — 2.11.0

- Source: https://packagist.org/packages/masterminds/html5
- License: **MIT** (`vendor/masterminds/html5/LICENSE.txt`)
- Copyright © 2014 Masterminds.
- MIT notice: permission is hereby granted, free of charge, to any person
  obtaining a copy of this software and associated documentation files
  (the "Software"), to deal in the Software without restriction, including
  without limitation the rights to use, copy, modify, merge, publish,
  distribute, sublicense, and/or sell copies of the Software, and to permit
  persons to whom the Software is furnished to do so, subject to the
  conditions of the included license text (THE SOFTWARE IS PROVIDED "AS IS",
  WITHOUT WARRANTY OF ANY KIND).
- Distributed **unmodified**.

## sabberworm/php-css-parser — v9.5.0

- Source: https://packagist.org/packages/sabberworm/php-css-parser
- License: **MIT** (`vendor/sabberworm/php-css-parser/LICENSE`)
- Copyright © 2011 Raphael Schweikert, sabberworm.com.
- MIT notice as per the included license text; distributed **unmodified**.

## Bundled fonts

- **DejaVu font family** (Sans/Serif/Mono faces and metrics) ships inside
  `vendor/dompdf/dompdf/lib/fonts/` under the permissive, redistribution-
  friendly license that accompanies the DejaVu fonts (Bitstream Vera
  derived — see `vendor/dompdf/dompdf/lib/fonts/mustRead.html` and the
  package's font licensing notes). Copyright © Bitstream Inc., Tavmjong
  Bah, and DejaVu contributors. Rendered PDFs embed **subsets** of these
  fonts (subsetting enabled).

## Build-time tools (not distributed in the runtime ZIP)

- **esbuild** (MIT) and **TypeScript** (Apache-2.0) are frontend
  *devDependencies* used to compile `assets/admin.js`/`admin.css`; they are
  not included in any distributed artifact. Their outputs are our own code.

## GPL notice for the whole distribution

This program is free software; you can redistribute it and/or modify it
under the terms of the GNU General Public License as published by the Free
Software Foundation; either version 2 of the License, or (at your option)
any later version. See `LICENSE` for the full text. Purchasing a
distribution buys convenient packaging, documentation, updates and
support — it does not remove the freedoms the GPL and the above licenses
grant you.
