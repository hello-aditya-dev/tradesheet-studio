"use client"

import * as React from "react"
import { motion, useReducedMotion } from "framer-motion"

import { cn } from "@/lib/utils"

type RevealProps = React.ComponentProps<typeof motion.div> & {
  delay?: number
  y?: number
}

/**
 * Subtle scroll-reveal wrapper: fades and slides content in the first time
 * it enters the viewport. Respects the user's reduced-motion preference.
 */
export function Reveal({ children, className, delay = 0, y = 18, ...props }: RevealProps) {
  const reduceMotion = useReducedMotion()

  return (
    <motion.div
      className={className}
      initial={{ opacity: 0, y: reduceMotion ? 0 : y }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-64px" }}
      transition={{ duration: 0.55, delay, ease: [0.21, 0.47, 0.32, 0.98] }}
      {...props}
    >
      {children}
    </motion.div>
  )
}
