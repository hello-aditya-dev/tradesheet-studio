"use client"

import * as React from "react"
import { motion } from "framer-motion"
import { AlertTriangle, BadgeCheck, Check, GitBranch, ShieldCheck } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card } from "@/components/ui/card"
import { Label } from "@/components/ui/label"
import { Switch } from "@/components/ui/switch"
import { cn } from "@/lib/utils"
import { formatINR } from "@/components/site/demo/demo-data"

const QUOTED_PRICE = 4450
const RAISED_PRICE = 4984 // +12% simulated store price change

export function StepIntegrity({
  simulate,
  decision,
  onSimulateChange,
  onDecision,
}: {
  simulate: boolean
  decision: "honor" | "propose-new" | null
  onSimulateChange: (value: boolean) => void
  onDecision: (decision: "honor" | "propose-new") => void
}) {
  return (
    <div className="flex flex-col gap-4">
      <div>
        <h3 className="text-base font-semibold tracking-tight text-stone-900">
          Step 4 — Integrity check
        </h3>
        <p className="mt-1 text-sm text-stone-600">
          Before the quote becomes an agreement, the integrity engine compares
          it against the frozen snapshot — and against the store as it stands
          right now.
        </p>
      </div>

      <Card className="gap-0 border-stone-200 py-0">
        <div className="flex items-center justify-between gap-4 p-5">
          <div className="flex flex-col gap-1">
            <Label
              htmlFor="simulate-switch"
              className="text-sm font-medium text-stone-800"
            >
              Simulate: store price changes while negotiating
            </Label>
            <p className="text-xs text-stone-500">
              Raises the store price of VW-LED-HB150 by 12% after the quote was
              requested.
            </p>
          </div>
          <Switch
            id="simulate-switch"
            checked={simulate}
            onCheckedChange={onSimulateChange}
            aria-describedby="simulate-switch-hint"
            className="data-[state=checked]:bg-teal-700"
          />
        </div>
      </Card>

      {!simulate && (
        <Card className="gap-0 border-teal-200 bg-teal-50/50 py-0">
          <div className="flex items-start gap-3 p-5">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-teal-700 text-white">
              <ShieldCheck className="size-4.5" aria-hidden="true" />
            </span>
            <div>
              <p className="flex items-center gap-2 text-sm font-semibold text-stone-900">
                <BadgeCheck className="size-4 text-teal-700" aria-hidden="true" />
                Integrity check passed
              </p>
              <p className="mt-1 text-sm text-stone-600">
                The quote matches snapshot{" "}
                <span className="font-mono text-stone-700">TSS-P-1042 · v1</span>{" "}
                — prices, SKUs and quantity rules all line up. Nothing to
                resolve.
              </p>
            </div>
          </div>
        </Card>
      )}

      {simulate && (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.3 }}
          className="flex flex-col gap-4"
        >
          <Card className="gap-0 border-amber-300 py-0">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-amber-200 bg-amber-100/60 px-5 py-3">
              <p className="flex items-center gap-2 text-sm font-semibold text-amber-900">
                <AlertTriangle className="size-4" aria-hidden="true" />
                Discrepancy detected — quote vs. current store
              </p>
              <Badge variant="outline" className="border-amber-400 bg-amber-100 text-amber-900">
                Warning
              </Badge>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-stone-200 text-xs tracking-wide text-stone-500 uppercase">
                    <th className="px-5 py-2 font-medium">Item</th>
                    <th className="py-2 font-medium">Quoted</th>
                    <th className="py-2 font-medium">Current</th>
                    <th className="px-5 py-2 font-medium">Drift</th>
                  </tr>
                </thead>
                <tbody>
                  <tr className="border-b border-stone-100 last:border-b-0">
                    <td className="px-5 py-2.5">
                      <p className="font-mono text-xs text-stone-500">VW-LED-HB150</p>
                      <p className="text-stone-800">LED High Bay 150W 5000K</p>
                    </td>
                    <td className="py-2.5 font-mono text-stone-700">
                      {formatINR(QUOTED_PRICE)}
                    </td>
                    <td className="py-2.5 font-mono text-stone-700">
                      {formatINR(RAISED_PRICE)}
                    </td>
                    <td className="px-5 py-2.5">
                      <Badge variant="outline" className="border-amber-400 bg-amber-100 text-amber-900">
                        +12%
                      </Badge>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </Card>

          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-stone-600">
              The merchant decides how the revision handles the drift:
            </p>
            <div className="flex flex-wrap gap-2">
              <Button
                onClick={() => onDecision("honor")}
                variant={decision === "honor" ? "default" : "outline"}
                className={cn(
                  "h-10 px-5",
                  decision === "honor"
                    ? "bg-teal-700 text-white hover:bg-teal-800"
                    : "border-stone-300 bg-white text-stone-700 hover:bg-stone-100 hover:text-stone-900"
                )}
                aria-pressed={decision === "honor"}
              >
                {decision === "honor" ? (
                  <>
                    <Check aria-hidden="true" />
                    Quoted price honored
                  </>
                ) : (
                  "Honor quoted price"
                )}
              </Button>
              <Button
                onClick={() => onDecision("propose-new")}
                variant={decision === "propose-new" ? "default" : "outline"}
                className={cn(
                  "h-10 px-5",
                  decision === "propose-new"
                    ? "bg-teal-700 text-white hover:bg-teal-800"
                    : "border-stone-300 bg-white text-stone-700 hover:bg-stone-100 hover:text-stone-900"
                )}
                aria-pressed={decision === "propose-new"}
              >
                {decision === "propose-new" ? (
                  <>
                    <Check aria-hidden="true" />
                    New price proposed
                  </>
                ) : (
                  "Propose new price"
                )}
              </Button>
            </div>
          </div>

          {decision && (
            <motion.div
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.3 }}
            >
              <Card className="gap-0 border-teal-200 bg-teal-50/50 py-0">
                <div className="flex flex-col gap-2 p-5">
                  <Badge
                    variant="outline"
                    className="w-fit border-teal-300 bg-white px-3 py-1 text-xs text-teal-800"
                  >
                    <GitBranch aria-hidden="true" />
                    Revision R2 issued — buyer must accept explicitly
                  </Badge>
                  <p className="text-sm leading-relaxed text-stone-600">
                    {decision === "honor" ? (
                      <>
                        R2 keeps the high bay at the quoted{" "}
                        <span className="font-mono text-stone-800">
                          {formatINR(QUOTED_PRICE)}
                        </span>{" "}
                        — the merchant absorbs the mid-negotiation drift.
                      </>
                    ) : (
                      <>
                        R2 proposes the high bay at{" "}
                        <span className="font-mono text-stone-800">
                          {formatINR(RAISED_PRICE)}
                        </span>{" "}
                        — the current store price, offered transparently.
                      </>
                    )}{" "}
                    A material seller modification never becomes an accepted
                    agreement without the buyer&apos;s explicit acceptance of
                    that revision.
                  </p>
                </div>
              </Card>
            </motion.div>
          )}
        </motion.div>
      )}
    </div>
  )
}
