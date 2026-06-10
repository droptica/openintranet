# Creating Books and Knowledge Base Pages

How to add new books to the Knowledge Base so they behave exactly like the demo content
(book tree in the left sidebar, child-page buttons, search).

## Create a new book

1. Go to **Create → Knowledge Base Page** (do **not** use the "Book page" content type — the
   demo Knowledge Base is built entirely from Knowledge Base Pages).
2. Fill in the title and body.
3. Open the **Book outline** section in the form sidebar and select **"- Create a new book -"**.
4. Save. The page becomes the top-level page of a new book and shows the Knowledge Base
   navigation sidebar, with the new book included in the tree.

## Add pages inside a book

- On any book page, click **Add child page** (or **Add sibling page**). The new node is created
  as a Knowledge Base Page with the right place in the outline pre-selected — just fill in the
  content and save.
- Alternatively create a **Knowledge Base Page** manually and pick the target book/parent in its
  **Book outline** section.

## Reorder or move pages

- Edit the page and change **Parent item** / **Weight** in the **Book outline** section, or use
  the book outline UI under **Structure → Books** (`/admin/structure/book`).

## Tips

- The Knowledge Base sidebar lists every book on the site; each book expands to its own tree.
- A page can belong to only one book outline.
- To link a book in the main "Knowledge Base" menu, add a menu link pointing to the book's
  top-level page (Structure → Menus → Main navigation).
