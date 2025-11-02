import { ref  } from 'vue';

const visible = ref(false)
const title = ref('')
const buttonLabel = ref('')
const contentComponent = ref(null)
const submitAction = ref(null)

export function useModal() {
  const openModal = ({ modalTitle, buttonText, component, onSubmit }) => {
    title.value = modalTitle || 'Modal'
    buttonLabel.value = buttonText || 'Submit'
    contentComponent.value = component || null
    submitAction.value = onSubmit || null
    visible.value = true
  }

  const closeModal = () => {
    visible.value = false
    title.value = ''
    buttonLabel.value = ''
    contentComponent.value = null
    submitAction.value = null
  }

  const submitModal = () => {
    if (submitAction.value) submitAction.value()
  }

  return {
    visible,
    title,
    buttonLabel,
    contentComponent,
    openModal,
    closeModal,
    submitModal,
  }
}
