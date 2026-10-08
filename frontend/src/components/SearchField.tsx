import { useState } from 'react';
import { Search } from 'lucide-react';

/**
 * Pill search box that submits on Enter / the search button and clears the
 * applied query when emptied. Uncontrolled from the caller's side: remount it
 * (`key={value}`) when the applied value changes elsewhere (e.g. "Clear filters").
 */
export function SearchField({ id, label, placeholder, value, onSearch }: { id: string; label: string; placeholder: string; value: string; onSearch: (q: string) => void }) {
  const [draft, setDraft] = useState(value);
  return (
    <form
      role="search"
      aria-label={label}
      className="relative"
      onSubmit={(e) => {
        e.preventDefault();
        onSearch(draft.trim());
      }}
    >
      <label htmlFor={id} className="sr-only">
        {label}
      </label>
      <input
        id={id}
        type="search"
        value={draft}
        onChange={(e) => {
          setDraft(e.target.value);
          if (e.target.value === '' && value) onSearch('');
        }}
        placeholder={placeholder}
        className="h-control w-[min(18rem,80vw)] rounded-pill border border-control bg-surface pl-4 pr-12 text-body text-primary placeholder:text-placeholder hover:border-control-hover"
      />
      <button type="submit" aria-label="Search" className="absolute right-1 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full text-secondary hover:bg-hover-nav">
        <Search aria-hidden="true" className="h-icon w-icon" />
      </button>
    </form>
  );
}
