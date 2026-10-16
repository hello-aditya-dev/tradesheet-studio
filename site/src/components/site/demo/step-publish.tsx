"use client"

import * as React from "react"
import { motion } from "framer-motion"
import { Check, Fingerprint, Lock } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card } from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import {
  PACKET_EXPIRES,
  PACKET_ID,
  PACKET_ISSUED,
  PACKET_VERSION,
  SIMULATED_CHECKSUM,
  formatINR,
  type DemoProduct,
} from "@/components/site/demo/demo-data"

type Phase = "idle" | "checking" | "frozen"

export function StepPublish({
  selectedProducts,
  published,
  onPublish,
}: {
  selectedProducts: DemoProduct[]
  published: boolean
  onPublish: () => void
}) {
  const [phase, setPhase] = React.useState<Phase>(published ? "frozen" : "idle")
  const [checksShown, setChecksShown] = React.useState(published ? PREFLIGHT.length : 0)

  React.useEffect(() => {
    if (phase !== "checking") return
    if (checksShown >= PREFLIGHT.length) {
      const done = window.setTimeout(() => {
        setPhase("frozen")
        onPublish()
      }, 450)
      return () => window.clearTimeout(done)
    }
    const timer = window.setTimeout(() => {
      setChecksShown((shown) => shown + 1)
    }, 420)
    return () => window.clearTimeout(timer)
  }, [phase, checksShown, onPublish])

  const progress = Math.round((checksShown / PREFLIGHT.length) * 100)

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h3 className="text-base font-semibold tracking-tight text-stone-900">
          Step 2 — Publish (freeze the snapshot)
        </h3>
        <p className="mt-1 text-sm text-stone-600">
          Publication is atomic: preflight checks run first, and the issued
          version is immutable — changes create a new version.
        </p>
      </div>

      {phase === "idle" && (
        <Card className="gap-0 border-stone-200 py-0">
          <div className="flex flex-col items-start gap-4 p-6">
            <p className="text-sm text-stone-600">
              Ready to issue{" "}
              <span className="font-mono text-stone-800">
                {PACKET_ID} · {PACKET_VERSION}
              </span>{" "}
              to Krishna Electricals with{" "}
              <strong className="font-semibold text-stone-800">
                {selectedProducts.length} line item{selectedProducts.length === 1 ? "" : "s"}
              </strong>
              .
            </p>
            <Button
              onClick={() => setPhase("checking")}
              className="h-10 bg-teal-700 px-5 text-white hover:bg-teal-800"
            >
              <Fingerprint aria-hidden="true" />
              Run preflight &amp; freeze snapshot
            </Button>
          </div>
        </Card>
      )}

      {phase === "checking" && (
        <Card className="gap-0 border-stone-200 py-0">
          <div className="p-6">
            <p className="mb-4 flex items-center gap-2 text-sm font-semibold text-stone-900">
              <span
                aria-hidden="true"
                className="inline-block size-2.5 animate-pulse rounded-full bg-amber-500"
              />
              Preflight — freezing…
            </p>
            <Progress
              value={progress}
              className="h-1.5 bg-stone-200 [&>div]:bg-amber-500"
              aria-label="Preflight progress"
            />
            <ul className="mt-4 flex flex-col gap-2.5">
              {PREFLIGHT.slice(0, checksShown).map((check) => (
                <motion.li
                  key={check}
                  initial={{ opacity: 0, x: -10 }}
                  animate={{ opacity: 1, x: 0 }}
                  className="flex items-center gap-2.5 text-sm text-stone-700"
                >
                  <span className="flex size-5 shrink-0 items-center justify-center rounded-full bg-teal-100">
                    <Check className="size-3 text-teal-800" aria-hidden="true" />
                  </span>
                  {check}
                </motion.li>
              ))}
            </ul>
          </div>
        </Card>
      )}

      {phase === "frozen" && (
        <motion.div
          initial={{ opacity: 0, scale: 0.985 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ duration: 0.35, ease: "easeOut" }}
        >
          <Card className="gap-0 overflow-hidden border-amber-300 bg-amber-50/40 py-0 shadow-md shadow-amber-100">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200 bg-amber-100/70 px-5 py-3.5">
              <p className="flex items-center gap-2 text-sm font-semibold text-amber-900">
                <Lock className="size-4" aria-hidden="true" />
                Snapshot frozen — immutable
              </p>
              <div className="flex flex-wrap gap-2">
                <Badge variant="outline" className="border-amber-300 bg-white text-amber-900">
                  {PACKET_ID} · {PACKET_VERSION}
                </Badge>
                <Badge variant="outline" className="border-stone-300 bg-white font-mono text-stone-700">
                  sha256: {SIMULATED_CHECKSUM} (simulated)
                </Badge>
              </div>
            </div>

            <div className="grid gap-3 border-b border-amber-200 px-5 py-3.5 sm:grid-cols-3">
              <div>
                <p className="text-xs text-stone-500">Issued</p>
                <p className="text-sm font-medium text-stone-800">{PACKET_ISSUED}</p>
              </div>
              <div>
                <p className="text-xs text-stone-500">Expires</p>
                <p className="text-sm font-medium text-stone-800">{PACKET_EXPIRES}</p>
              </div>
              <div>
                <p className="text-xs text-stone-500">Buyer</p>
                <p className="text-sm font-medium text-stone-800">Krishna Electricals</p>
              </div>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-stone-200 text-xs tracking-wide text-stone-500 uppercase">
                    <th className="px-5 py-2 font-medium">SKU</th>
                    <th className="py-2 font-medium">Item</th>
                    <th className="py-2 font-medium">Qty breaks</th>
                    <th className="px-5 py-2 text-right font-medium">Frozen buyer price</th>
                  </tr>
                </thead>
                <tbody>
                  {selectedProducts.map((product) => (
                    <tr key={product.sku} className="border-b border-stone-100 last:border-b-0">
                      <td className="px-5 py-2 font-mono text-xs text-stone-600">
                        {product.sku}
                      </td>
                      <td className="py-2 text-stone-800">{product.name}</td>
                      <td className="py-2 text-xs text-stone-500">
                        {product.breaks.length === 0
                          ? "—"
                          : product.breaks
                              .map((tier) => `${tier.qty}+ ${formatINR(tier.price)}`)
                              .join(" · ")}
                      </td>
                      <td className="px-5 py-2 text-right font-mono font-semibold text-teal-800">
                        {formatINR(product.wholesalePrice)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <p className="px-5 py-3.5 text-xs leading-relaxed text-stone-500">
              Prices, quantity rules and validity are now read-only. Need
              different terms later? That becomes{" "}
              <span className="font-mono text-stone-700">v2</span> — this version
              is never rewritten. Simulated checksum shown for illustration.
            </p>
          </Card>
        </motion.div>
      )}
    </div>
  )
}

const PREFLIGHT = [
  "Price book resolved — Distributor Tier A",
  "Line items mapped — every SKU exists and is purchasable",
  "Quantity rules attached — minimums and breaks recorded",
  "SHA-256 checksum computed over the snapshot",
  "Validity window set — 30 days, expiring 14 Mar 2026",
] as const
