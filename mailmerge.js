/*
 * Copyright (c) 2023 bbecker
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

function mailmerge() {
    form = window.document
    formData = new FormData()

    formData.append('mode', rcmail.editor.is_html() ? "html" : "txt")
    formData.append('message', rcmail.editor.get_content())
    formData.append('subject', $("[name='_subject']", form).val().trim())
    formData.append('from', $("[name='_from']", form).val())

    input_to = $("[name='_to']", form).val().trim().split(",").map(addr => addr.trim())
    formData.append('to', input_to)

    input_cc = $("[name='_cc']", form).val().trim().split(",").map(addr => addr.trim())
    formData.append('cc', input_cc)

    input_bcc = $("[name='_bcc']", form).val().trim().split(",").map(addr => addr.trim())
    formData.append('bcc', input_bcc)

    input_replyto = $("[name='_replyto']", form).val().trim().split(",").map(addr => addr.trim())
    formData.append('replyto', input_replyto)

    input_followupto = $("[name='_followupto']", form).val().trim().split(",").map(addr => addr.trim())
    formData.append('followupto', input_followupto)

    formData.append("separator", $("#mailmergesep").val())
    formData.append("enclosed",  document.querySelector("#mailmergeencl").checked)
    formData.append("csv", document.querySelector("#mailmergefile").files[0], "data.csv")

    compose_id = URL.parse(window.location.href).searchParams.get("_id")
    formData.append("compose_id", compose_id)

    formData.append("_mdn", document.querySelector("[name=_mdn]").checked);
    formData.append("_dsn", document.querySelector("[name=_dsn]").checked);
    formData.append("_priority", document.querySelector("[name=_priority]").value);

    console.log(formData)

    $.ajax({
        type: 'POST', url: rcmail.url("plugin.mailmerge"), data: formData,
        contentType: false,
        processData: false,
        success: function(data) { rcmail.http_response(data) },
        error: function(o, status, err) { rcmail.http_error(o, status, err, false, "plugin.mailmerge") }
    })
}

rcmail.addEventListener('init', function(evt) {
    console.log(evt)
    rcmail.register_command("plugin.mailmerge", mailmerge, true)
    rcmail.env.compose_commands.push("plugin.mailmerge")
});