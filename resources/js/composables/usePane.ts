import { ref, shallowRef, type Component } from 'vue';

export type PaneSide = 'top' | 'right' | 'bottom' | 'left';

export interface PaneOptions {
  title?: string;
  side?: PaneSide;
  component?: Component | null;
  componentProps?: Record<string, unknown>;
  loading?: boolean;
  error?: string | null;
}

const open = ref(false);
const title = ref('');
const side = ref<PaneSide>('right');
const loading = ref(false);
const error = ref<string | null>(null);
const contentComponent = shallowRef<Component | null>(null);
const componentProps = ref<Record<string, unknown>>({});

function resetPaneState() {
  title.value = '';
  side.value = 'right';
  loading.value = false;
  error.value = null;
  contentComponent.value = null;
  componentProps.value = {};
}

export function usePane() {
  // Right pane (drawer) state for dynamic content.
  const paneVisible = ref(false);
  const paneTitle = ref('');
  const paneSide = ref<PaneSide>('right');
  const paneLoading = ref(false);
  const paneError = ref<string | null>(null);
  const paneContentComponent = shallowRef<Component | null>(null);
  const paneComponentProps = ref<Record<string, any>>({});
  
  const closePane = () => {
    paneVisible.value = false;
    paneLoading.value = false;
    paneTitle.value = '';
    paneError.value = null;
    paneContentComponent.value = null;
    paneComponentProps.value = {};
  };

  const openPane = (options: {
    title: string;
    side: PaneSide;
    component: Component | null;
    componentProps?: Record<string, any>;
  }) => {
    paneTitle.value = options.title;
    paneSide.value = options.side ?? 'right';
    paneContentComponent.value = options.component;
    paneComponentProps.value = options.componentProps ?? {};
    paneLoading.value = false;
    paneError.value = null;
    paneVisible.value = true;
  };
  // const openPane = (options: PaneOptions = {}) => {
  //   console.log(options);
  //   title.value = options.title ?? '';
  //   side.value = options.side ?? 'right';
  //   loading.value = options.loading ?? false;
  //   error.value = options.error ?? null;
  //   contentComponent.value = options.component ?? null;
  //   componentProps.value = options.componentProps ?? {};
  //   open.value = true;
  // };

  // const closePane = () => {
  //   open.value = false;
  //   setTimeout(() => {
  //     resetPaneState();
  //   }, 150);
  // };

  const setPaneLoading = (value: boolean) => {
    loading.value = value;
  };

  const setPaneError = (value: string | null) => {
    error.value = value;
  };

  const setPaneContent = (
    component: Component | null,
    props: Record<string, unknown> = {},
  ) => {
    contentComponent.value = component;
    componentProps.value = props;
  };

  return {
    open,
    title,
    side,
    loading,
    error,
    contentComponent,
    componentProps,
    openPane,
    closePane,
    setPaneLoading,
    setPaneError,
    setPaneContent,
  };
}
