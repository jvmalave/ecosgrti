import { TestBed } from '@angular/core/testing';
import { provideAnimationsAsync } from '@angular/platform-browser/animations/async';
import { of } from 'rxjs';
import { UnifiedPersonModalComponent } from './unified-person-modal.component';
import { UnifiedPersonService } from '../../data-access/services/unified-person.service';

describe('UnifiedPersonModalComponent', () => {
  beforeEach(async () => {
    const mockUnifiedPersonService = {
      searchPersons: () => of([]),
      getPersons: () => of({ data: [], meta: {} }),
      createPerson: () => of({}),
      updatePerson: () => of({}),
    };

    await TestBed.configureTestingModule({
      imports: [UnifiedPersonModalComponent],
      providers: [
        provideAnimationsAsync(),
        {
          provide: UnifiedPersonService,
          useValue: mockUnifiedPersonService,
        },
      ],
    }).compileComponents();
  });

  it('should create', () => {
    const fixture = TestBed.createComponent(UnifiedPersonModalComponent);
    const component = fixture.componentInstance;
    expect(component).toBeTruthy();
  });
});