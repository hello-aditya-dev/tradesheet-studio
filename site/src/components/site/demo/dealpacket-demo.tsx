"use client"

import * as React from "react"
import { AnimatePresence, motion, useReducedMotion } from "framer-motion"
import { Check, ChevronLeft, ChevronRight, RotateCcw } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Progress } from "@/components/ui/progress"
import { useToast } from "@/hooks/use-toast"
import { cn } from "@/lib/utils"
import {
  DEMO_DEFAULT_QTY,
  DEMO_PRODUCTS,
  isQtyValid,
  parseQty,
} from "@/components/site/demo/demo-data"
import { StepCompose } from "@/components/site/demo/step-compose"
import { StepPublish } from "@/components/site/demo/step-publish"
import { StepQuote } from "@/components/site/demo/step-quote"
import { StepIntegrity } from "@/components/site/demo/step-integrity"
import { StepOrder } from "@/components/site/demo/step-order"

const STEPS = [
  { id: 1, label: "Compose" },
  { id: 2, label: "Publish" },
  { id: 3, label: "Quote" },
  { id: 4, label: "Integrity" },
  { id: 5, label: "Order" },
] as const

type MerchantDecision = "honor" | "propose-new"

type DemoState = {
  selected: string[]
  qtyInput: Record<string, string>
  published: boolean
  quoteQty: Record<string, number> | null
  simulatePriceChange: boolean
  decision: MerchantDecision | null
}

type DemoAction =
  | { type: "TOGGLE_PRODUCT"; sku: string }
  | { type: "SET_QTY"; sku: string; value: string }
  | { type: "PUBLISH" }
  | { type: "SUBMIT_QUOTE" }
  | { type: "TOGGLE_SIMULATE"; value: boolean }
  | { type: "SET_DECISION"; decision: MerchantDecision }
  | { type: "RESET" }

function createInitialState(): DemoState {
  return {
    selected: DEMO_PRODUCTS.map((product) => product.sku),
    qtyInput: { ...DEMO_DEFAULT_QTY },
    published: false,
    quoteQty: null,
    simulatePriceChange: false,
    decision: null,
  }
}

function demoReducer(state: DemoState, action: DemoAction): DemoState {
  switch (action.type) {
    case "TOGGLE_PRODUCT": {
      const isSelected = state.selected.includes(action.sku)
      return {
        ...state,
        selected: isSelected
          ? state.selected.filter((sku) => sku !== action.sku)
          : [...state.selected, action.sku],
      }
    }
    case "SET_QTY":
      return { ...state, qtyInput: { ...state.qtyInput, [action.sku]: action.value } }
    case "PUBLISH":
      return { ...state, published: true }
    case "SUBMIT_QUOTE": {
      if (state.quoteQty) return state
      const quoteQty: Record<string, number> = {}
      for (const product of DEMO_PRODUCTS) {
        if (state.selected.includes(product.sku)) {
          const qty = parseQty(state.qtyInput[product.sku])
          if (!Number.isNaN(qty) && isQtyValid(product, qty)) {
            quoteQty[product.sku] = qty
          }
        }
      }
      return { ...state, quoteQty }
    }
    case "TOGGLE_SIMULATE":
      return { ...state, simulatePriceChange: action.value }
    case "SET_DECISION":
      return { ...state, decision: action.decision }
    case "RESET":
      return createInitialState()
    default:
      return state
  }
}

export function DealPacketDemo() {
  const [state, dispatch] = React.useReducer(demoReducer, undefined, createInitialState)
  const [step, setStep] = React.useState(1)
  const { toast } = useToast()
  const reduceMotion = useReducedMotion()

  const selectedProducts = DEMO_PRODUCTS.filter((product) =>
    state.selected.includes(product.sku)
  )

  const anyInvalidQty = selectedProducts.some((product) => {
    const qty = parseQty(state.qtyInput[product.sku])
    return !isQtyValid(product, qty)
  })

  const canAdvance =
    step === 1
      ? state.selected.length > 0
      : step === 2
        ? state.published
        : step === 3
          ? state.quoteQty !== null && !anyInvalidQty
          : step === 4
            ? !state.simulatePriceChange || state.decision !== null
            : false

  const gatingHint =
    step === 1 && state.selected.length === 0
      ? "Select at least one product to continue."
      : step === 2 && !state.published
        ? "Run the preflight check and freeze the snapshot to continue."
        : step === 3 && state.quoteQty === null
          ? anyInvalidQty
            ? "Fix the quantity errors, then submit the quote request."
            : "Submit the quote request to continue."
          : step === 4 && state.simulatePriceChange && state.decision === null
            ? "Resolve the price discrepancy to continue."
            : null

  function goTo(target: number) {
    setStep(Math.min(Math.max(target, 1), STEPS.length))
  }

  function handleNext() {
    if (step === 5) return
    if (!canAdvance) return
    goTo(step + 1)
  }

  function handleRestart() {
    dispatch({ type: "RESET" })
    setStep(1)
  }

  return (
    <Card className="gap-0 overflow-hidden border-stone-200 py-0">
      <CardHeader className="border-b border-stone-200 bg-gradient-to-r from-teal-50 to-stone-50 [.border-b]:pb-5">
        <CardTitle className="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-lg tracking-tight text-stone-900 md:text-xl">
          DealPacket walkthrough
          <span className="font-mono text-sm font-normal text-teal-700">
            Volt &amp; Wire Trading Co.
          </span>
          <span className="rounded-md border border-amber-300 bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-900">
            Synthetic demo data
          </span>
        </CardTitle>
        <CardDescription>
          Simulated in your browser — not connected to a live WooCommerce store.
        </CardDescription>
      </CardHeader>

      <CardContent className="p-0">
        {/* Stepper */}
        <div className="border-b border-stone-200 px-5 pt-5 pb-4 sm:px-6">
          <ol className="flex items-start" aria-label="Demo steps">
            {STEPS.map((stepDef, index) => {
              const isComplete = stepDef.id < step
              const isCurrent = stepDef.id === step
              const isClickable = stepDef.id < step
              return (
                <li key={stepDef.id} className="flex flex-1 items-start last:flex-none">
                  <div className="flex flex-col items-center gap-1.5">
                    <button
                      type="button"
                      onClick={() => isClickable && goTo(stepDef.id)}
                      disabled={!isClickable}
                      aria-current={isCurrent ? "step" : undefined}
                      aria-label={`Step ${stepDef.id}: ${stepDef.label}${isComplete ? " (completed)" : isCurrent ? " (current)" : ""}`}
                      className={cn(
                        "flex size-8 items-center justify-center rounded-full border-2 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700 focus-visible:ring-offset-2",
                        isComplete && "border-teal-700 bg-teal-700 text-white hover:bg-teal-800",
                        isCurrent && "border-teal-700 bg-white text-teal-700",
                        !isComplete && !isCurrent && "border-stone-200 bg-white text-stone-400"
                      )}
                    >
                      {isComplete ? <Check className="size-4" aria-hidden="true" /> : stepDef.id}
                    </button>
                    <span
                      className={cn(
                        "hidden text-xs font-medium sm:block",
                        isCurrent ? "text-teal-800" : "text-stone-500"
                      )}
                    >
                      {stepDef.label}
                    </span>
                  </div>
                  {index < STEPS.length - 1 && (
                    <div
                      aria-hidden="true"
                      className={cn(
                        "mx-1 mt-4 h-0.5 flex-1 rounded-full sm:mx-2",
                        stepDef.id < step ? "bg-teal-700" : "bg-stone-200"
                      )}
                    />
                  )}
                </li>
              )
            })}
          </ol>
          <Progress
            value={(step - 1) * 25}
            className="mt-4 h-1.5 bg-stone-200 [&>div]:bg-teal-700"
            aria-label={`Demo progress: step ${step} of ${STEPS.length}`}
          />
        </div>

        {/* Step content */}
        <div className="min-h-[26rem] px-5 py-6 sm:px-6">
          <AnimatePresence mode="wait" initial={false}>
            <motion.div
              key={step}
              initial={{ opacity: 0, y: reduceMotion ? 0 : 12 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: reduceMotion ? 0 : -12 }}
              transition={{ duration: 0.28, ease: "easeOut" }}
            >
              {step === 1 && (
                <StepCompose
                  selected={state.selected}
                  onToggle={(sku) => dispatch({ type: "TOGGLE_PRODUCT", sku })}
                />
              )}
              {step === 2 && (
                <StepPublish
                  selectedProducts={selectedProducts}
                  published={state.published}
                  onPublish={() => dispatch({ type: "PUBLISH" })}
                />
              )}
              {step === 3 && (
                <StepQuote
                  selectedProducts={selectedProducts}
                  qtyInput={state.qtyInput}
                  quoteQty={state.quoteQty}
                  onQtyChange={(sku, value) =>
                    dispatch({ type: "SET_QTY", sku, value })
                  }
                  onSubmit={() => {
                    dispatch({ type: "SUBMIT_QUOTE" })
                    setStep(4)
                  }}
                />
              )}
              {step === 4 && (
                <StepIntegrity
                  simulate={state.simulatePriceChange}
                  decision={state.decision}
                  onSimulateChange={(value) =>
                    dispatch({ type: "TOGGLE_SIMULATE", value })
                  }
                  onDecision={(decision) =>
                    dispatch({ type: "SET_DECISION", decision })
                  }
                />
              )}
              {step === 5 && (
                <StepOrder
                  selectedProducts={selectedProducts}
                  quoteQty={state.quoteQty}
                  simulate={state.simulatePriceChange}
                  decision={state.decision}
                  onAcceptAgain={() =>
                    toast({
                      title: "No duplicate order created",
                      description:
                        "This quote has already been converted — the submission is idempotent, so a repeated accept cannot create a second order.",
                    })
                  }
                  onRestart={handleRestart}
                />
              )}
            </motion.div>
          </AnimatePresence>
        </div>

        {/* Footer navigation */}
        <div className="flex flex-col gap-3 border-t border-stone-200 bg-stone-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <Button
            variant="outline"
            onClick={() => goTo(step - 1)}
            disabled={step === 1}
            className="h-10 border-stone-300 hover:bg-stone-100 hover:text-stone-900"
          >
            <ChevronLeft aria-hidden="true" />
            Back
          </Button>

          <div className="flex flex-col items-start gap-2 sm:items-end">
            {gatingHint && (
              <p className="text-xs text-amber-700" role="status">
                {gatingHint}
              </p>
            )}
            <div className="flex gap-2">
              {step === 5 && (
                <Button
                  variant="outline"
                  onClick={handleRestart}
                  className="h-10 border-stone-300 hover:bg-stone-100 hover:text-stone-900"
                >
                  <RotateCcw aria-hidden="true" />
                  Restart demo
                </Button>
              )}
              {step < 5 && (
                <Button
                  onClick={handleNext}
                  disabled={!canAdvance}
                  className="h-10 bg-teal-700 px-5 text-white hover:bg-teal-800"
                >
                  {step === 4 ? "Convert to order" : "Continue"}
                  <ChevronRight aria-hidden="true" />
                </Button>
              )}
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
