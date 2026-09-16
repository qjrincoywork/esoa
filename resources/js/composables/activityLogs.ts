import { toRef } from 'vue';
import { dispatchNotification } from '@/components/notification';
import { useAjax } from '@/composables/useAjax';
import { showLoader, hideLoader } from '@/composables/useLoader';
import { useModulePermissions } from '@/composables/useModulePermissions';
import { usePane } from '@/composables/usePane';
import ActivityLogDetailPane from '@/components/forms/activity_logs/ActivityLogDetailPane.vue';

/** One row of the audit trail, as `ActivityLogListResource` shapes it. */
export interface ActivityLogRow {
  id: number;
  logged_at: string | null;
  logged_at_value: string | null;
  log_name: string;
  module: string;
  event: string | null;
  event_label: string | null;
  description: string | null;
  subject_type: string | null;
  subject_id: number | string | null;
  causer: string | null;
  causer_id: number | null;
  batch_uuid: string | null;
  change_count: number;
}

/** One field that changed, already rendered for display by the server. */
export interface ActivityLogChange {
  field: string;
  label: string;
  from: string | null;
  to: string | null;
}

/** The request an entry was written on, when it came from one. */
export interface ActivityLogContext {
  ip?: string;
  user_agent?: string;
  route?: string;
  method?: string;
}

/** A single entry opened up, as `ActivityLogDetailResource` shapes it. */
export interface ActivityLogDetail extends ActivityLogRow {
  causer_email: string | null;
  changes: ActivityLogChange[];
  context: ActivityLogContext | null;
  batch_siblings: ActivityLogRow[];
}

export function useActivityLogs() {
  const { slug } = useModulePermissions();
  const { get } = useAjax();
  const {
    openPane,
    closePane,
    setPaneLoading,
    setPaneError,
    setPaneContent,
    rightPane,
  } = usePane();

  const rightPaneVisible = toRef(rightPane, 'open');
  const rightPaneTitle = toRef(rightPane, 'title');
  const rightPaneLoading = toRef(rightPane, 'loading');
  const rightPaneError = toRef(rightPane, 'error');
  const rightPaneContentComponent = toRef(rightPane, 'contentComponent');
  const rightPaneComponentProps = toRef(rightPane, 'componentProps');

  /**
   * Fetch one entry with its field-by-field changes.
   *
   * The listing carries no snapshots, so the detail is pulled only for the entry
   * actually opened.
   */
  const getActivityLog = async (id: number | string): Promise<ActivityLogDetail | null> => {
    try {
      const response = await get<{ activity_log: ActivityLogDetail }>(`/${slug.value}/${id}/show`);

      if (!response.ok) {
        throw new Error('Failed to fetch the activity log entry');
      }

      return response.data?.activity_log ?? null;
    } catch {
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });

      return null;
    }
  };

  /**
   * Open an entry in the right pane.
   *
   * The row is passed through as well, so the pane has something to show immediately
   * and while the detail is on its way.
   */
  const openActivityLog = async (row: ActivityLogRow) => {
    showLoader();

    try {
      const detail = await getActivityLog(row.id);

      openPane({
        side: 'right',
        title: `${row.module} · ${row.event_label ?? 'Activity'}`,
        component: ActivityLogDetailPane,
        componentProps: { row, detail },
      });
    } catch {
      setPaneLoading('right', false);
      setPaneError('right', 'Error fetching the activity log entry.');
      setPaneContent('right', null);
      dispatchNotification({ title: 'Error', content: 'Error fetching data', type: 'error' });
    } finally {
      hideLoader();
    }
  };

  return {
    getActivityLog,
    openActivityLog,
    closePane,
    rightPaneVisible,
    rightPaneTitle,
    rightPaneLoading,
    rightPaneError,
    rightPaneContentComponent,
    rightPaneComponentProps,
  };
}
