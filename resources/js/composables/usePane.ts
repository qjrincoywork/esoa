import { reactive, type Component } from 'vue';

export type PaneSide = 'top' | 'right' | 'bottom' | 'left';

export interface PaneOptions {
  title?: string;
  side?: PaneSide;
  component?: Component | null;
  componentProps?: Record<string, unknown>;
  loading?: boolean;
  error?: string | null;
}

interface PaneState {
  open: boolean;
  title: string;
  loading: boolean;
  error: string | null;
  contentComponent: Component | null;
  componentProps: Record<string, unknown>;
}

const createPaneState = (): PaneState => ({
  open: false,
  title: '',
  loading: false,
  error: null,
  contentComponent: null,
  componentProps: {},
});

const panes = reactive<Record<PaneSide, PaneState>>({
  top: createPaneState(),
  right: createPaneState(),
  bottom: createPaneState(),
  left: createPaneState(),
});

function resetPaneState(pane: PaneState) {
  pane.open = false;
  pane.title = '';
  pane.loading = false;
  pane.error = null;
  pane.contentComponent = null;
  pane.componentProps = {};
}

export function usePane() {
  const openPane = (options: PaneOptions = {}) => {
    const side = options.side ?? 'right';
    const pane = panes[side];

    pane.title = options.title ?? '';
    pane.loading = options.loading ?? false;
    pane.error = options.error ?? null;
    pane.contentComponent = options.component ?? null;
    pane.componentProps = options.componentProps ?? {};
    pane.open = true;
  };

  const closePane = (side?: PaneSide) => {
    if (side) {
      resetPaneState(panes[side]);
      return;
    }

    resetPaneState(panes.top);
    resetPaneState(panes.right);
    resetPaneState(panes.bottom);
    resetPaneState(panes.left);
  };

  const setPaneLoading = (side: PaneSide, value: boolean) => {
    panes[side].loading = value;
  };

  const setPaneError = (side: PaneSide, value: string | null) => {
    panes[side].error = value;
  };

  const setPaneContent = (
    side: PaneSide,
    component: Component | null,
    props: Record<string, unknown> = {},
  ) => {
    panes[side].contentComponent = component;
    panes[side].componentProps = props;
  };

  const topPane = panes.top;
  const rightPane = panes.right;
  const bottomPane = panes.bottom;
  const leftPane = panes.left;

  return {
    openPane,
    closePane,
    setPaneLoading,
    setPaneError,
    setPaneContent,
    topPane,
    rightPane,
    bottomPane,
    leftPane,
  };
}
