/**
 * Synthetic demo data for the interactive DealPacket walkthrough.
 * "Volt & Wire Trading Co." (merchant) and "Krishna Electricals" (buyer)
 * are fictional demonstration businesses — no real data anywhere.
 */

export type DemoProduct = {
  sku: string
  name: string
  listPrice: number
  wholesalePrice: number
  breaks: { qty: number; price: number }[]
  minQty: number
  step: number
}

export const DEMO_PRODUCTS: DemoProduct[] = [
  {
    sku: "VW-LED-HB150",
    name: "LED High Bay 150W 5000K",
    listPrice: 5199,
    wholesalePrice: 4450,
    breaks: [
      { qty: 10, price: 4180 },
      { qty: 50, price: 3950 },
    ],
    minQty: 2,
    step: 2,
  },
  {
    sku: "VW-LED-TP36",
    name: "LED Triproof Batten 36W",
    listPrice: 1480,
    wholesalePrice: 1240,
    breaks: [{ qty: 20, price: 1150 }],
    minQty: 1,
    step: 1,
  },
  {
    sku: "VW-DB-8WAY",
    name: "Distribution Board 8-Way TPN IP65",
    listPrice: 4650,
    wholesalePrice: 3890,
    breaks: [],
    minQty: 1,
    step: 1,
  },
  {
    sku: "VW-MCB-C32",
    name: "MCB C32 1P 10kA",
    listPrice: 495,
    wholesalePrice: 412,
    breaks: [{ qty: 50, price: 385 }],
    minQty: 1,
    step: 1,
  },
  {
    sku: "VW-CAB-2R5",
    name: "Copper Cable 2.5mm² 90m Coil",
    listPrice: 6999,
    wholesalePrice: 5999,
    breaks: [],
    minQty: 1,
    step: 1,
  },
  {
    sku: "VW-SKT-IEC32",
    name: "Industrial Socket 32A 3P+E IP67",
    listPrice: 899,
    wholesalePrice: 760,
    breaks: [{ qty: 25, price: 705 }],
    minQty: 1,
    step: 1,
  },
]

export const DEMO_DEFAULT_QTY: Record<string, string> = {
  "VW-LED-HB150": "6",
  "VW-LED-TP36": "24",
  "VW-DB-8WAY": "2",
  "VW-MCB-C32": "60",
  "VW-CAB-2R5": "1",
  "VW-SKT-IEC32": "30",
}

export const PACKET_ID = "TSS-P-1042"
export const PACKET_VERSION = "v1"
export const PACKET_ISSUED = "12 Feb 2026"
export const PACKET_EXPIRES = "14 Mar 2026"
export const SIMULATED_CHECKSUM = "9f2c…41ab"

/** Deterministic Indian-rupee formatting (lakh/crore grouping). */
export function formatINR(amount: number): string {
  const fixed = Math.abs(amount).toFixed(2)
  const [intPart, decPart] = fixed.split(".")
  let grouped: string
  if (intPart.length <= 3) {
    grouped = intPart
  } else {
    const last3 = intPart.slice(-3)
    const rest = intPart.slice(0, -3)
    grouped = `${rest.replace(/\B(?=(\d{2})+(?!\d))/g, ",")},${last3}`
  }
  return `₹${grouped}.${decPart}`
}

/** Best unit price for a quantity, applying quantity breaks. */
export function resolveUnitPrice(product: DemoProduct, qty: number): number {
  let price = product.wholesalePrice
  for (const breakTier of product.breaks) {
    if (qty >= breakTier.qty) {
      price = breakTier.price
    }
  }
  return price
}

export function savingsPercent(product: DemoProduct): number {
  return Math.round((1 - product.wholesalePrice / product.listPrice) * 100)
}

export function parseQty(value: string | undefined): number {
  const parsed = Number.parseInt(value ?? "", 10)
  return Number.isNaN(parsed) ? NaN : parsed
}

export function isQtyValid(product: DemoProduct, qty: number): boolean {
  if (Number.isNaN(qty)) return false
  return qty >= product.minQty && qty % product.step === 0
}

export function qtyError(product: DemoProduct): string {
  if (product.step > 1) {
    return `Minimum ${product.minQty} · multiples of ${product.step}`
  }
  return `Minimum ${product.minQty}`
}
