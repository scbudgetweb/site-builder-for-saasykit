# Media

**Where:** Admin › Website › Media
**Who can use it:** the **Library** tab, anyone with the *update pages* permission; the **Images** and **Audio** tabs, administrators with the *update settings* permission

Three tabs:

- **Library:** every image and sound file uploaded to your pages.
- **Images** and **Audio:** what can be uploaded.

The tab is in the page's address (`/admin/media?tab=images`), so a link or a refresh opens the same tab.

## Library

Everything uploaded to your pages shows here: images, SVG drawings and sound files, whether they were uploaded in a block, in a page's settings or here. Branding's logos and banner images aren't included; they live in [Branding](branding.md).

- **Grid or List:** thumbnails, or a table with each file's size, dimensions and when it was uploaded. **Grid** and **List** are at the top of the page, beside **Upload**, and your choice is remembered.
- **Search** by the file's name, and **filter** by kind (images, SVG drawings, sound files), by whether it's **in use**, and by the date it was **uploaded**.
- **Upload:** add several images and sound files at once. Each is checked as it would be in a block: its type and size (from the [Images](#images) and [Audio](#audio) tabs), and an SVG must be a plain drawing.
- **Details** (click a file): see it larger (or play a sound file), with its size, dimensions, who uploaded it and when, and its link, which you can copy. You can change:
  - its **Name**, which is only shown in the library, to find it again (its link doesn't change);
  - for an image, its **Alternative text**: a short description of the picture for screen readers and search engines.
- **Used on** (in Details) lists every page, library block and template that uses the file, with a link to each. Unsaved visual builder drafts and kept [revisions](revisions.md) that use it are counted too.
- **Download** saves the file to your computer, under its name in the library.

### Deleting files

A file stays in the library until someone deletes it. Removing an image from a page doesn't delete the file, so you can use it again later.

**Delete** (in a file's menu) is greyed out while a page (including one in the bin), a library block, a template or a visual builder draft uses the file; Details lists where. To clear out old files, filter by **In use: Not used anywhere**, select them and choose **Delete**. Files in use are kept and you're told how many.

A kept revision doesn't stop a file being deleted. Restoring that revision afterwards shows nothing where the file was.

## Images

What image fields on your pages take. **Save changes** saves this tab.

- **File types:** JPG, PNG, WebP, AVIF, GIF and SVG. All but GIF are on as standard. An SVG stays sharp at any size, and is only taken when it's a plain drawing, with no scripts or links. A share image is never an SVG, because social sites don't show them.
- **Largest image:** 2 MB as standard, up to 100 MB. A photo straight from a phone is often 3 to 6 MB.
- **When a photo is uploaded** (JPG, PNG and WebP):
  - **Shrink large photos:** make wider photos 1,600, 1,920, 2,560 or 3,840 pixels wide, keeping their shape, so pages load faster. With this on, you can raise the largest image and let people upload photos straight from their phone.
  - **Remove location data:** phones save where a photo was taken inside it. This saves photos again without it (or the rest of the camera details), so a published photo can't give away someone's home, say. Photos are also turned the right way up.

  GIFs (which may move), AVIF, SVG drawings and very large photos (over 40 million pixels) are kept as uploaded, as are photos uploaded before either was turned on. Both options need Laravel 13 and Intervention Image 4 (`composer require intervention/image:^4.0`), with PHP's GD extension. Without them, the page says so and the options are greyed out.

## Audio

What [Audio blocks](pages.md#media) take. **Save changes** saves this tab.

- **File types:** MP3, M4A / AAC, OGG / Opus, WAV, FLAC and WebM audio. The first four are on as standard: MP3 plays in every browser, and M4A, OGG and WAV in every current one. FLAC and WebM don't play everywhere, so turn on the block's *Offer a download link* when you use them. Only plain sound formats are offered, because uploads are served from your site's own address.
- **Largest sound file:** 100 MB as standard, up to 2048 MB. An hour's talk at 128 kbps is about 60 MB, so a podcast episode usually fits.

## Your server's limit

Your server has its own upload limit, set by PHP's `upload_max_filesize` and `post_max_size`. The Images and Audio tabs show it under the size. When it's lower than the size you choose, uploads stop at the server's limit, and the page says so in orange. Only your host can raise it. If large files still fail to upload, ask about the web server's own limit too (such as nginx's `client_max_body_size`).

Livewire, which handles uploads in the admin, has a limit of its own (12 MB as standard). The site builder raises it to your largest image or sound file, and gives an upload up to 30 minutes to finish. Developers can turn that off with `site-builder.livewire_upload_size`.
