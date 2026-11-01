# Media

**Where:** Admin › Website › Media
**Who can use it:** administrators with the *update settings* permission

What can be uploaded to your pages. Each kind of file has its own tab.

## Audio

Sets the sound files [Audio blocks](pages.md#media) take.

- **File types:** MP3, M4A / AAC, OGG / Opus, WAV, FLAC and WebM audio. The first four are on as standard: MP3 plays in every browser, and M4A, OGG and WAV in every current one. FLAC and WebM don't play everywhere, so turn on the block's *Offer a download link* when you use them. Only plain sound formats are offered, because uploads are served from your site's own address.
- **Largest sound file:** 100 MB as standard, up to 2048 MB. An hour's talk at 128 kbps is about 60 MB, so a podcast episode usually fits.

### Your server's limit

Your server has its own upload limit, set by PHP's `upload_max_filesize` and `post_max_size`. The page shows it under the size. When it's lower than the size you choose, Audio blocks take files up to the server's limit, and the page says so in orange. Only your host can raise it. If large files still fail to upload, ask about the web server's own limit too (such as nginx's `client_max_body_size`).

Livewire, which handles uploads in the admin, has a limit of its own (12 MB as standard). The site builder raises it to your largest sound file, and gives an upload up to 30 minutes to finish. Developers can turn that off with `site-builder.livewire_upload_size`.

A sound file nothing uses any more is deleted, as images are.
