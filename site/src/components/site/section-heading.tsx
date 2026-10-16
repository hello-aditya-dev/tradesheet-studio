import * as React from "react"

import { cn } from "@/lib/utils"
import { Reveal } from "@/components/site/reveal"

type SectionHeadingProps = {
  id: string
  eyebrow: string
  title: string
  intro?: React.ReactNode
  align?: "left" | "center"
  className?: string
}

/**
 * Consistent section header: small amber-dashed eyebrow, tight display
 * heading, and an optional intro paragraph.
 */
export function SectionHeading({
  id,
  eyebrow,
  title,
  intro,
  align = "center",
  className,
}: SectionHeadingProps) {
  return (
    <Reveal
      className={cn(
        "mb-10 flex max-w-3xl flex-col gap-4 md:mb-14",
        align === "center" && "mx-auto items-center text-center",
        className
      )}
    >
      <p className="flex items-center gap-2 text-xs font-semibold tracking-[0.18em] text-teal-700 uppercase">
        <span aria-hidden="true" className="inline-block h-px w-6 bg-amber-500" />
        {eyebrow}
        {align === "center" && (
          <span aria-hidden="true" className="inline-block h-px w-6 bg-amber-500" />
        )}
      </p>
      <h2
        id={id}
        className="text-3xl font-semibold tracking-tight text-stone-900 text-balance md:text-4xl"
      >
        {title}
      </h2>
      {intro ? (
        <p className="text-base leading-relaxed text-stone-600 text-pretty md:text-lg">
          {intro}
        </p>
      ) : null}
    </Reveal>
  )
}
