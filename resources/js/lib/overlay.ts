/**
 * Helpers for overlays that are teleported to `<body>` but must behave as if they sit
 * ABOVE an open Reka UI layer (the Sheet behind `RightPane` / `TopPane`, or any Dialog).
 *
 * A Reka modal layer deliberately makes everything outside its own DOM subtree inert:
 * it sets `pointer-events: none` on `<body>`, traps focus inside itself, and dismisses
 * itself on any outside interaction. An overlay teleported to `<body>` is "outside" by
 * that definition, so it stacks correctly yet cannot be clicked or typed into — and the
 * first click that does land closes the pane underneath it.
 *
 * Marking the overlay root with {@link GLOBAL_OVERLAY_ATTR} lets those panes recognise
 * such interactions as their own instead of as a dismissal.
 */

/** Attribute stamped on the root of an overlay that stacks above the pane layer. */
export const GLOBAL_OVERLAY_ATTR = 'data-global-overlay';

const GLOBAL_OVERLAY_SELECTOR = `[${GLOBAL_OVERLAY_ATTR}]`;

/** Outside-interaction payload emitted by Reka's dismissable layers. */
type OutsideInteractionEvent = CustomEvent<{ originalEvent: Event }>;

/** True when the event target lives inside an overlay stacked above the pane. */
export function isInsideGlobalOverlay(target: EventTarget | null): boolean {
  return target instanceof Element && target.closest(GLOBAL_OVERLAY_SELECTOR) !== null;
}

/**
 * Keep a Reka Sheet/Dialog open when the interaction came from an overlay stacked above it.
 *
 * Bind to `@interact-outside`, which Reka emits for both outside pointer-downs and outside
 * focus moves; calling `preventDefault()` is the documented way to veto the dismissal.
 */
export function keepOpenForGlobalOverlay(event: OutsideInteractionEvent): void {
  if (isInsideGlobalOverlay(event.detail?.originalEvent?.target ?? null)) {
    event.preventDefault();
  }
}
