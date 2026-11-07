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
  notes: string
  text: string
  photos: PhotoView[]
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
  createdAt: string
  sentAt: string | null
}

export interface IssueView extends Omit<IssueSummary, 'sections'> {
  sections: SectionView[]
}

export interface ConfigView {
  defaultName: string
  maxPhotos: number
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
