import { usePath } from './hooks/usePath'
import { IssueEditorPage } from './pages/IssueEditorPage'
import { IssueListPage } from './pages/IssueListPage'

/** Deux écrans : / (les numéros) et /numeros/:id (rédaction d'un numéro). */
export function App() {
  const [path, navigate] = usePath()
  const match = /^\/numeros\/([0-9a-f]{8})$/.exec(path)

  if (match?.[1]) {
    return <IssueEditorPage id={match[1]} onBack={() => navigate('/')} />
  }

  return <IssueListPage onOpen={(id) => navigate(`/numeros/${id}`)} />
}
