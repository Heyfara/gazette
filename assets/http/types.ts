// Ce que renvoie l'API (src/Controller/IssueView.php, ImmichController.php).

export type IssueStatus = 'brouillon' | 'teste' | 'envoye'

export interface PhotoView {
  assetId: string
  takenAt: string | null
  city: string | null
  country: string | null
  people: string[]
  caption: string
}

export interface SectionView {
  id: string
  title: string
  text: string
  photos: PhotoView[]
}

/** Une note du carnet du mois (date AAAA-MM-JJ) */
export interface NoteView {
  id: string
  date: string
  text: string
}

export interface IssueSummary {
  id: string
  number: number
  name: string
  title: string
  month: string
  status: IssueStatus
  editable: boolean
  sections: number
  photos: number
  /** Notes dans le carnet du mois */
  notes: number
  createdAt: string
  sentAt: string | null
  /** Campagne Listmonk, une fois le numéro préparé */
  campaignId: number | null
}

export interface IssueView extends Omit<IssueSummary, 'sections' | 'notes'> {
  sections: SectionView[]
  notes: NoteView[]
}

export interface SendConfigView {
  configured: boolean
  list: string
  template: string
  testEmails: string[]
}

export interface ConfigView {
  defaultName: string
  maxPhotos: number
  send: SendConfigView
}

/** État de la campagne côté Listmonk (null tant que le numéro n'est pas préparé). */
export interface CampaignState {
  id: number
  status: string
  toSend: number
  sent: number
}

export interface AlbumView {
  id: string
  name: string
  count: number
  start: string | null
  end: string | null
  coverAssetId: string | null
}

export interface PersonView {
  id: string
  name: string
}

export interface AssetView {
  id: string
  takenAt: string | null
  city: string | null
  country: string | null
  people: string[]
}

export interface SearchPage {
  assets: AssetView[]
  /** Photos de cette page (Immich ne donne pas le total) */
  count: number
  nextCursor: string | null
}

/** Une photo telle que le front la renvoie au serveur (PATCH rubrique). */
export interface PhotoInput {
  assetId: string
  caption: string
}
