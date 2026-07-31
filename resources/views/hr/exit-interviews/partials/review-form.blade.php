<form method="POST" action="{{ route('hr.exit-interviews.review',$interview) }}" class="people-form-grid">@csrf @method('PATCH')
    <label class="people-field is-wide"><span>HR review notes</span><textarea class="people-control" name="hr_review_notes" required></textarea></label>
    <label class="people-field"><span>Action owner</span><input class="people-control" name="action_items[0][owner]"></label>
    <label class="people-field"><span>Follow-up action</span><input class="people-control" name="action_items[0][action]"></label>
    <label class="people-field"><span>Action due date</span><input class="people-control" type="date" name="action_items[0][due_on]"></label>
    <button class="people-button is-primary" type="submit">Complete HR review</button>
</form>
